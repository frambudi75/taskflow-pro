import * as ftp from "basic-ftp"
import fs from "fs"
import path from "path"

const client = new ftp.Client()
const excludes = ['.git', 'node_modules', 'vendor', 'cache', 'logs', 'deploy.mjs', 'test_ftp.php', '.env.example', 'TODO.md', 'TODO2.md', 'CHANGELOG.md', 'db_dump.php'];

// Additional excludes for .sql files
const isExcluded = (name) => {
    if (excludes.includes(name)) return true;
    if (name.endsWith('.sql')) return true;
    return false;
}

async function uploadDir(localDir) {
    const entries = fs.readdirSync(localDir, { withFileTypes: true });
    
    for (const entry of entries) {
        if (isExcluded(entry.name)) continue;
        
        const localPath = path.join(localDir, entry.name);
        
        if (entry.isDirectory()) {
            console.log(`Creating directory: ${entry.name}`);
            await client.ensureDir(entry.name);
            await uploadDir(localPath);
            await client.cd("..");
        } else {
            console.log(`Uploading: ${localPath}`);
            await client.uploadFrom(localPath, entry.name);
        }
    }
}

async function deploy() {
    try {
        await client.access({
            host: "192.168.1.182",
            user: "taskflow",
            password: "sup3rmaN",
            secure: false
        })
        console.log("Terhubung ke server FTP. Memulai proses upload...");
        
        client.trackProgress(info => {
            console.log(`Transferring ${info.name}: ${info.bytesOverall} bytes`);
        });

        await uploadDir(".");
        console.log("Upload selesai dengan sukses!");
    } catch(err) {
        console.log("FTP Error:", err)
    }
    client.close()
}

deploy();
