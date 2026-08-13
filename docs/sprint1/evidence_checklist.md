# Smart Gym Management System
## Sprint 1 Evidence Checklist

### Project
World Fitness Australia – Smart Gym Management System

### Assessment
ICT308 Project 2 – Assessment 1  
Iteration 1 Development and Progress Demonstration

---

## 1. Development Environment Evidence

- [x] XAMPP installed and configured
- [x] Apache running successfully
- [x] MySQL/MariaDB running successfully
- [x] localhost accessible
- [x] PHP test page running successfully
- [x] Project stored inside XAMPP htdocs

Evidence available:
- XAMPP localhost screenshot
- Smart Gym PHP test screenshot

---

## 2. GitHub Evidence

- [x] Smart Gym GitHub repository created
- [x] main branch created
- [x] develop branch created
- [x] Feature branches created
- [x] main branch protection configured
- [x] Pull request workflow used
- [x] Authentication feature merged into develop
- [x] Authentication testing documentation merged into develop
- [x] Integration testing documentation merged into develop

Feature branches:

- feature/backend-authentication
- feature/frontend-foundation
- feature/database-foundation
- feature/testing-documentation
- feature/system-integration

Evidence available:
- GitHub branch screenshot
- Branch protection screenshot
- Pull request screenshots
- Merge screenshots
- GitHub commit history

---

## 3. Jira Evidence

- [x] Jira Scrum project created
- [x] Sprint 1 created
- [x] Sprint goal defined
- [x] Team members assigned
- [x] Story points added
- [x] Tasks moved through Jira workflow
- [x] Completed tasks moved to Approved

Jira workflow:

To Do → In Design → User Testing → Approved

Sprint 1 tasks include:

- SGMS-1 GitHub repository and branch structure
- SGMS-2 XAMPP and PHP development environment
- SGMS-3 MySQL database schema
- SGMS-4 Login, registration and dashboard interfaces
- SGMS-5 Member registration backend
- SGMS-6 Login, logout and role-based sessions
- SGMS-7 Authentication testing
- SGMS-8 System integration
- SGMS-9 Sprint documentation and evidence
- SGMS-10 PHP/MySQL database connection

Evidence available:
- Jira board screenshots
- Sprint task screenshots
- Approved task evidence

---

## 4. Database Evidence

- [x] smart_gym database created
- [x] Database SQL script created
- [x] users table created
- [x] members table created
- [x] trainers table created
- [x] memberships table created
- [x] classes table created
- [x] bookings table created
- [x] payments table created
- [x] attendance table created
- [x] notifications table created
- [x] Foreign key relationships implemented
- [x] Database connection tested successfully

Evidence available:
- phpMyAdmin database structure screenshot
- Table list screenshot
- Database connection test screenshot
- database/smart_gym.sql

---

## 5. Frontend Evidence

- [x] Homepage created
- [x] Login interface created
- [x] Registration interface created
- [x] Member Dashboard created
- [x] Trainer Dashboard created
- [x] Administrator Dashboard created
- [x] World Fitness Australia branding added
- [x] Responsive styling implemented
- [x] Navigation tested

Evidence available:
- Homepage screenshot
- Login page screenshot
- Registration page screenshot
- Member Dashboard screenshot
- Trainer Dashboard screenshot
- Admin Dashboard screenshot

---

## 6. Member Registration Evidence

- [x] Registration form connected to PHP backend
- [x] Required field validation
- [x] Email validation
- [x] Duplicate email prevention
- [x] Password confirmation validation
- [x] Password strength validation
- [x] Membership selection validation
- [x] Terms acceptance validation
- [x] Secure password hashing
- [x] Prepared SQL statements
- [x] Database transaction used
- [x] User record created
- [x] Member record created
- [x] Membership record created

Evidence available:
- Successful registration screenshot
- Duplicate email validation screenshot
- Password validation screenshots
- phpMyAdmin user/member/membership screenshots

---

## 7. Authentication Evidence

- [x] Secure login implemented
- [x] password_verify() used
- [x] Session management implemented
- [x] Session ID regenerated after authentication
- [x] Logout implemented
- [x] Member role redirect working
- [x] Trainer role redirect working
- [x] Administrator role redirect working
- [x] Protected dashboards implemented
- [x] Role-based access control implemented

Evidence available:
- Member login/dashboard screenshot
- Trainer login/dashboard screenshot
- Admin login/dashboard screenshot
- Access denied screenshots
- Logout/protected-page screenshot

---

## 8. Security Testing Evidence

- [x] Duplicate email registration tested
- [x] Mismatched password tested
- [x] Short password tested
- [x] Terms validation tested
- [x] Incorrect login password tested
- [x] Invalid email tested
- [x] Password hashing verified
- [x] Protected dashboard after logout tested
- [x] Member blocked from Admin area
- [x] Member blocked from Trainer area
- [x] Trainer blocked from Admin area
- [x] Trainer blocked from Member area
- [x] Admin blocked from Trainer area
- [x] Admin blocked from Member area

Testing document:

tests/authentication_test_cases.md

---

## 9. Integration Testing Evidence

- [x] Frontend registration connected to PHP backend
- [x] Backend connected to MySQL database
- [x] Registration creates user record
- [x] Registration creates member profile
- [x] Registration creates membership
- [x] Newly registered account can log in
- [x] Role-based redirect works
- [x] Dashboard loads authenticated database data
- [x] Logout terminates session

Testing document:

tests/integration_test_cases.md

Evidence available:
- Integration registration screenshot
- users table screenshot
- members table screenshot
- memberships table screenshot
- Integration Test Member dashboard screenshot

---

## 10. Sprint 1 Completion Evidence

Completed and verified:

- [x] Development environment
- [x] Repository structure
- [x] Database foundation
- [x] Frontend interfaces
- [x] Secure registration
- [x] Authentication
- [x] Role-based access
- [x] Member Dashboard
- [x] Trainer Dashboard
- [x] Admin Dashboard
- [x] Authentication testing
- [x] System integration testing
- [x] GitHub pull request workflow
- [x] Jira Sprint workflow

Remaining documentation activity:

- [ ] Organise Sprint 1 screenshots
- [ ] Prepare technical report
- [ ] Add GitHub repository link
- [ ] Add Jira project link
- [ ] Add APA references
- [ ] Add GenAI acknowledgement
- [ ] Prepare demonstration speaking plan