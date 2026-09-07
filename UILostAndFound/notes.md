

Security Features 

Password hashing (bcrypt)
Input sanitization (XSS prevention)
Prepared statements (SQL injection prevention)
CSRF token protection
File upload validation
Role-based access control
Session management

Set Admin To User

UPDATE users SET role = 'admin' WHERE username = 'YOUR_USERNAME';