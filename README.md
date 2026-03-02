# Newsportal Manager 📰

**Newsportal Manager** is a lightweight yet powerful WordPress plugin designed to streamline frontend news submissions, editorial workflows, and user engagement. It transforms a standard WordPress site into a functional news portal with a dedicated dashboard for Admins, Reporters, and Subscribers.

## ✨ Key Features

- **Dynamic Frontend Dashboard:** A centralized hub for users to manage their profiles, view statistics, and track activity without accessing the WP Admin area.
- **Reporter Editorial System:** - Custom "Reporter" user role.
  - Frontend news submission form (Title, Category, Tags, Featured Image, and Rich Text Editor).
  - Post status tracking (Pending/Published).
- **Advanced Admin Controls:**
  - One-click post approval/publishing from the frontend.
  - Quick comment moderation (Approve/Delete) directly from the dashboard.
- **Real-Time Notification System:** - Facebook-style bell icon with live unread counts.
  - Instant alerts for new pending posts, comment approvals, and replies.
- **Analytics & Tracking:** - Daily site visitor tracking.
  - Individual post view counters for authors.
- **Custom Authentication:** Enhanced registration modal that includes a password field and AJAX-based login/registration.

## 📂 Project Structure

```text
newsportal-manager/
├── assets/
│   ├── css/
│   │   └── custom-styles.css    # Dashboard UI & Notification styling
│   └── js/
│       └── custom-scripts.js    # AJAX handlers & Real-time UI updates
├── custom-dashboard-pro.php       # Main Plugin Controller
└── README.md                    # Documentation
