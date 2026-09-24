

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

Create:

report-lost.php inserts new lost items into the items table.
categories.php inserts new categories into the categories table.
auth.php creates new users during registration.
Read:

item.php reads an item by ID and loads related comments and claims.
users.php reads and filters users from the database.
search.php and dashboard.php also fetch records for display.
Update:

categories.php updates category details.
users.php updates user roles and active status.
item.php updates comments and increments item views.
approve-claim.php updates claim and item status.
Delete:

delete-item.php deletes related matches, claims, and the item itself.
users.php deletes users.
categories.php deletes categories.