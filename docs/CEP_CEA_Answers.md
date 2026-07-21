# Complex Engineering Problem & Complex Engineering Activities (CEP & CEA) Document

**Course:** CSE311L (Section 7) - Fall 2025  
**Project:** Barta – Safe Messaging Application for Tweens  
**Institution:** North South University

---

## Group Information

| Name | NSU ID | Email |
| :--- | :--- | :--- |
| **Aminul Islam** | 2321169042 | aminul.islam.232@northsouth.edu |
| **Naylah Hassan Chowdhury** | 2311531042 | naylah.chowdhury@northsouth.edu |
| **Maymuna Khanom** | 2232039642 | maymuna.khanom@northsouth.edu |

---

## CEP Attributes

### a) What knowledge did you need to work on this project?

To successfully develop Barta, we required knowledge across multiple domains:

1. **Database Design & SQL (from CSE311 Course):**
   - **Relational Database Design:** We designed a normalized database schema with multiple interconnected tables (`bartauser`, `tween_user`, `parent_user`, `message`, `individual_message`, `connection`, `connection_request`, `tween_link_request`, `blocked_word`) following proper normalization principles.
   - **SQL Query Writing:** Complex JOIN queries were written to fetch messages between users, manage friend connections, handle parent-child relationships, and filter flagged messages containing blocked words.
   - **Database Transactions:** We implemented transaction handling with `mysqli_begin_transaction()`, `mysqli_commit()`, and `mysqli_rollback()` to ensure data integrity during multi-table insertions (e.g., user registration).
   - **Foreign Key Constraints & Referential Integrity:** We established proper foreign key relationships with CASCADE operations for maintaining data consistency across parent-child linking and message relationships.

2. **Web Development Technologies (Self-Learned):**
   - **PHP (Server-Side Programming):** Used for session management, authentication, form handling, database operations, and building RESTful-like API endpoints for AJAX interactions.
   - **HTML5 & CSS3:** Used for structuring web pages semantically and implementing a modern, responsive UI with dark/light theme support, glassmorphism effects, and smooth animations.
   - **JavaScript (ES6+):** Implemented client-side interactivity including real-time messaging with long-polling, modular code architecture (using ES6 modules), DOM manipulation, and AJAX (fetch API) for asynchronous communication.

3. **Security Concepts:**
   - Password hashing using `password_hash()` and `password_verify()` with the bcrypt algorithm.
   - Input sanitization using `FILTER_SANITIZE_SPECIAL_CHARS` and `FILTER_VALIDATE_EMAIL` to prevent XSS attacks.
   - Session-based authentication and role-based access control (tween vs. parent).

4. **Foundational Knowledge (from Previous Courses):**
   - **CSE 173 (Discrete Mathematics):** Logical reasoning for designing the friend request approval workflow (requiring multiple approvals from receiver and both parents).
   - **CSE 215/215L (Programming Language II):** Object-oriented thinking applied in modular code organization (JavaScript modules: `msg.js`, `contacts.js`, `settings.js`, `ui.js`, `utils.js`, `state.js`).
   - **CSE 225/225L (Data Structures & Algorithms):** Understanding of efficient data handling for contact lists, message ordering (ASC/DESC), and search functionality.

---

### b) What unique way did you use to design this project?

Our project incorporates several unique design decisions that differentiate it from typical messaging applications:

1. **Supervised Messaging for Under-13 Users:**
   - Unlike most messaging platforms that restrict children under 13 (due to COPPA regulations), Barta provides a **controlled messaging environment** specifically designed for tweens (ages 8-12) under strict parental supervision. This addresses a real gap in the market where children need to communicate but lack safe platforms.

2. **Multi-Level Approval System for Friend Connections:**
   - Friend requests require a **three-way approval process**:
     1. Receiver's acceptance (`receiver_accepted`)
     2. Receiver's parent approval (`receiver_parent_approved`)
     3. Requester's parent approval (`requester_parent_approved`)
   - This ensures that no child can add friends without their parent's explicit consent.

3. **Unified Parent Dashboard for Multiple Children:**
   - Parents can manage **multiple linked children from a single dashboard**, viewing:
     - Pending link requests from children
     - Friend requests pending approval
     - Flagged messages containing blocked words
     - Daily message statistics (sent/received)
     - Per-child blocked word lists
     - Per-child daily message limits
   - This centralized approach simplifies parental oversight.

4. **Per-Child Customizable Blocked Words System:**
   - Parents can define **child-specific blocked word lists** rather than a global filter. When a message containing a blocked word is sent, it:
     - Gets flagged (`is_clean = 0`)
     - Requires parent approval (`parent_approval = 'pending'`)
     - Is shown to the child as "Waiting for approval..." until the parent approves or rejects it.

5. **Real-Time Messaging with Long Polling:**
   - We implemented **long-polling** for near real-time message updates without WebSockets, using PHP sleep loops with abort handling (`set_time_limit(0)`, `ignore_user_abort(true)`) and a 25-second timeout cycle.

6. **Parent-Initiated Tween Linking:**
   - Children cannot simply claim a parent. The tween sends a **link request to their parent's email**, and only after the parent approves does the child gain access to messaging features. This verification prevents unauthorized account linking.

7. **Progressive Account Activation:**
   - Tween accounts remain **inactive (`is_active = 0`)** until linked to a parent, enforcing the mandatory supervision model.

---

### c) What topics did you need to learn from previous courses that you were not familiar with?

While CSE311 focused on Database Design and SQL, the project required us to revisit and apply concepts from previous courses in new contexts:

1. **From CSE 173 (Discrete Mathematics):**
   - **Boolean Logic & Conditional Workflows:** We applied complex conditional logic for the approval system. For example, a friend connection is only established when:
     ```sql
     receiver_parent_approved = 1 AND requester_parent_approved = 1 AND receiver_accepted = 1
     ```
   - Understanding logical operators and state transitions helped design the approval flow.

2. **From CSE 215/215L (Programming Language II - Java):**
   - **Modular Programming & Code Organization:** Although Java-focused, the principles of separating concerns translated to our JavaScript module structure:
     - `state.js` – Application state management
     - `ui.js` – UI utilities (scrolling, selection, modals)
     - `msg.js` – Messaging functionality
     - `contacts.js` – Friend list and search operations
     - `settings.js` – User settings modal handling
     - `utils.js` – Cookie management and utility functions

3. **From CSE 225/225L (Data Structures & Algorithms):**
   - **List/Array Manipulation:** Used for iterating through friend lists, messages, and rendering contact previews with efficient truncation (`mb_substr` for safe string cutting).
   - **Sorting:** Messages are fetched in ascending order by `sent_at` for chronological display.
   - **Search Operations:** The tween search feature (`search_users.php`) performs partial username matching with proper handling of existing connections and pending requests.

4. **Self-Learning Requirements (Not from Previous Courses):**
   - **PHP:** Session management, password hashing, PDO-style prepared statements (used in `approve_message.php`).
   - **CSS Layouts:** Flexbox and CSS Grid for responsive layouts, CSS variables for theming.
   - **JavaScript Fetch API & Long Polling:** Asynchronous programming patterns for real-time updates.
   - **Web Security:** XSS prevention through input sanitization and output escaping with `htmlspecialchars()`.

---

## CEA Attributes

### a) Which resources did the project involve?

1. **Human Resources:**
   - **3 Team Members:** Each contributed to different aspects:
     - Backend PHP development and database design
     - Frontend HTML/CSS/JavaScript development
     - Testing, documentation, and UI polish
   - **Course Instructor & Lab Instructor:** Provided guidance on database design principles and project evaluation criteria.

2. **Modern Tools & Technologies:**
   - **Development Environment:**
     - **Laragon:** Local development stack (Apache, PHP 8.0.30, MariaDB 10.4.32)
     - **phpMyAdmin:** Database management and SQL execution
     - **Visual Studio Code:** Primary code editor with extensions (PHP Intelephense, Live Server)
   - **Version Control:**
     - **Git & GitHub:** Repository management at `Aminul-Islam7/barta-webapp-cse311`
   - **Design & Icons:**
     - **Font Awesome Pro:** Extensive icon library (jelly-fill, duotone, solid styles)
     - **Google Fonts (Inter):** Modern typography
   - **Hosting:**
     - **InfinityFree:** Free hosting at `barta.infinityfreeapp.com` for live deployment

3. **Financial Resources:**
   - **Minimal Cost:** The project was developed using free tools (VS Code, Laragon, GitHub) and free hosting (InfinityFree).
   - **Font Awesome Pro:** Utilized for premium icons (potentially through educational license or free tier).

4. **Time Investment:**
   - Approximately **4-5 weeks** of development time, including:
     - Database schema design and iteration
     - Backend API development
     - Frontend UI/UX implementation
     - Testing and bug fixing
     - Documentation

---

### b) What is the innovation?

Barta introduces several innovative solutions addressing a genuine gap in the children's digital communication space:

1. **Filling a Market Gap for Safe Under-13 Messaging:**
   - Most messaging platforms (WhatsApp, Messenger, Discord) prohibit users under 13 due to legal compliance concerns. Barta **specifically targets tweens (8-12 years)** by building parental controls as a core feature rather than an afterthought, enabling safe communication that was previously unavailable.

2. **Tri-Approval Friend Request System:**
   - Our unique **three-party approval system** for friend connections (child acceptance + both parents' approvals) provides unprecedented control over who children can communicate with, preventing stranger contact entirely.

3. **Context-Aware Content Moderation:**
   - The **per-child blocked words system** allows parents to customize filtering based on their child's maturity level and specific concerns, rather than applying a one-size-fits-all filter.

4. **Seamless Parent-Child Linking Workflow:**
   - The **email-based parent linking** system with pending/approved/denied states creates a verified parent-child relationship that cannot be bypassed, ensuring children cannot self-approve their account activation.

5. **Centralized Multi-Child Management:**
   - Parents managing multiple children (e.g., siblings Mary and Jack with parent Naylah in our test data) can oversee all linked children from a **single unified dashboard**, reducing friction in supervision.

6. **Transparent Message Flagging:**
   - Rather than silently blocking messages, Barta shows the sender that their message is **"Waiting for approval..."** when flagged, teaching children about appropriate communication while maintaining transparency.

7. **Daily Message Limits:**
   - Parents can set **per-child daily message limits** to encourage healthy screen time habits, with visual feedback showing remaining messages to the child.

8. **Modern, Kid-Friendly UI:**
   - The interface features **dark/light theme toggle**, animated icons (jelly-fill style), and an intuitive chat layout designed to be appealing and accessible for children while maintaining functionality for parents.

---

## Conclusion

Project Barta successfully demonstrates the practical application of database design principles taught in CSE311L while extending our skills into full-stack web development. By addressing a real-world problem—safe messaging for children—we created a solution that is both technically robust (with proper database normalization, transaction handling, and security measures) and socially responsible (with comprehensive parental controls). The project showcases our ability to integrate knowledge from multiple courses and self-learn new technologies to build a complete, functional web application.

---

*Document prepared on: December 20, 2025*
