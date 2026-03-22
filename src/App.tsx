/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { FileCode, Database, Server, Terminal, CheckCircle } from 'lucide-react';

export default function App() {
  return (
    <div className="min-h-screen bg-stone-50 flex items-center justify-center p-6">
      <div className="max-w-3xl w-full bg-white rounded-[3rem] shadow-2xl border border-stone-200 overflow-hidden">
        <div className="p-12 bg-stone-900 text-white">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-12 h-12 bg-emerald-500 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
              <FileCode size={28} />
            </div>
            <h1 className="text-3xl font-black tracking-tight">PHP Native Version Ready</h1>
          </div>
          <p className="text-stone-400 text-lg leading-relaxed">
            Aplikasi telah berhasil dikonversi ke **PHP Native** dengan **MySQL** dan **Tailwind CSS**. 
            Anda dapat menemukan semua file sumber di file explorer di sebelah kiri.
          </p>
        </div>

        <div className="p-12 space-y-10">
          <section>
            <h2 className="flex items-center gap-3 text-xl font-black text-stone-900 mb-6">
              <Database className="text-emerald-600" size={24} />
              1. Persiapan Database
            </h2>
            <div className="bg-stone-50 p-6 rounded-2xl border border-stone-100">
              <p className="text-sm text-stone-600 mb-4">
                Impor file <code className="bg-stone-200 px-2 py-0.5 rounded font-bold">database.sql</code> ke dalam 
                <strong> phpMyAdmin</strong> Anda (XAMPP/Laragon/aaPanel).
              </p>
              <div className="flex items-center gap-2 text-xs font-bold text-emerald-600 uppercase tracking-widest">
                <CheckCircle size={14} />
                Database: taskflow_db
              </div>
            </div>
          </section>

          <section>
            <h2 className="flex items-center gap-3 text-xl font-black text-stone-900 mb-6">
              <Server className="text-emerald-600" size={24} />
              2. Konfigurasi Server
            </h2>
            <div className="bg-stone-50 p-6 rounded-2xl border border-stone-100">
              <p className="text-sm text-stone-600 mb-4">
                Salin semua file PHP ke folder root server Anda (misal: <code className="bg-stone-200 px-2 py-0.5 rounded font-bold">C:/xampp/htdocs/taskflow</code>).
              </p>
              <p className="text-sm text-stone-600">
                Sesuaikan kredensial database di file <code className="bg-stone-200 px-2 py-0.5 rounded font-bold">config.php</code>.
              </p>
            </div>
          </section>

          <section>
            <h2 className="flex items-center gap-3 text-xl font-black text-stone-900 mb-6">
              <Terminal className="text-emerald-600" size={24} />
              3. Akses Aplikasi
            </h2>
            <div className="bg-stone-50 p-6 rounded-2xl border border-stone-100">
              <p className="text-sm text-stone-600 mb-4">Buka browser dan akses:</p>
              <code className="block bg-stone-900 text-emerald-400 p-4 rounded-xl font-mono text-sm">
                http://localhost/taskflow/index.php
              </code>
              <div className="mt-4 p-4 bg-emerald-50 rounded-xl border border-emerald-100">
                <p className="text-xs font-bold text-emerald-800 mb-1">Default Admin Login:</p>
                <p className="text-xs text-emerald-700">Email: admin@taskflow.com</p>
                <p className="text-xs text-emerald-700">Password: admin123</p>
              </div>
            </div>
          </section>
        </div>

        <div className="p-8 bg-stone-50 border-t border-stone-100 text-center">
          <p className="text-stone-400 text-xs font-bold uppercase tracking-widest">
            Dikembangkan dengan PHP Native & Tailwind CSS
          </p>
        </div>
      </div>
    </div>
  );
}
