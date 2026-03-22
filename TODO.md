# Fix Login Issue - TaskFlow

## Status: Plan Approved & Ready to Implement

**Issue:** Login fails "Email atau password salah" because database.sql has wrong password hash for default admin 'admin123'. Current hash is for 'password'.

**Root Cause:** Hash mismatch in default INSERT: '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' != password_hash('admin123').

**Correct hash:** $2y$12$t9AcitKLeUejH1C5VUzIwugkmpfUDAKJagsT/kQMPsRAqTV72gZ7i

## Steps:
- [x] Step 1: Edit database.sql to fix INSERT hash.
- [ ] Step 2: Re-import SQL to phpMyAdmin (drop taskflow_db first).
- [ ] Step 3: Test login: http://localhost/taskflow/login.php with admin@taskflow.com / admin123.
- [ ] Step 4: Verify dashboard loads (index.php).
- [ ] Step 5: Complete: attempt_completion
