# NewsPortal Manager

A custom WordPress plugin that turns a standard WordPress installation into a frontend-driven news publishing workflow. It provides registration, role-aware dashboards, reporter submissions, editorial moderation, notifications and lightweight traffic/post analytics without requiring reporters or subscribers to work inside the normal WordPress admin interface.

## Feature Overview

### Frontend Accounts

- AJAX-based user registration
- WordPress nonce validation on registration requests
- Automatic WordPress authentication after successful registration
- Frontend logout flow
- WordPress admin bar hidden from non-administrators on the frontend

### Role-Aware Dashboard

The `[my_custom_dashboard]` shortcode renders a dashboard based on the current user's WordPress role.

- **Administrator** — editorial queue, moderation controls and analytics
- **Reporter** — news submission, publishing status and author statistics
- **Subscriber** — account/activity-oriented notifications

### Reporter Publishing Workflow

Reporters can submit news from the frontend with:

- Title
- Category
- Tags
- Featured image
- Rich-text article body

Submissions use a WordPress nonce and integrate with the normal WordPress post model. The dashboard tracks pending/published content and provides the editorial side with moderation controls.

### Editorial Controls

Administrators can work from the frontend dashboard to:

- Review pending posts
- Open previews
- Open a post for editing
- Publish or trash submissions
- Review pending comments
- Approve or delete comments

### Notifications

The plugin builds role-specific activity notifications from WordPress data, including events such as:

- New pending posts for administrators
- Comment activity
- Reporter posts becoming live
- Replies to a reporter's comments
- Subscriber comment approvals
- Recently published posts

### Analytics

- Daily visitor counts stored in WordPress options
- Weekly and monthly site-visit summaries
- Per-post view counters
- Reporter total-view calculations
- Highest-performing post statistics

## Authentication Flow

Registration requests are sent to WordPress AJAX with a `custom_reg_nonce`. The server validates the nonce, sanitizes the username/email, checks for existing accounts, creates the user through `wp_insert_user()`, then establishes the normal WordPress logged-in session using `wp_set_current_user()` and `wp_set_auth_cookie()`.

The plugin therefore relies on **WordPress authentication cookies and the WordPress user system** rather than implementing a separate token/JWT authentication layer.

## Dashboard Flow

```text
Visitor
  │
  ├─ Register / authenticate through WordPress
  │
  ▼
[my_custom_dashboard]
  │
  ├─ Administrator → pending posts + comments + analytics
  ├─ Reporter      → submit news + track posts + analytics
  └─ Subscriber    → account/activity notifications
```

## Project Structure

```text
news-portal-manager/
├── custom-dashboard-pro.php
├── README.md
└── assets/
    ├── css/
    │   └── custom-styles.css
    └── js/
        └── custom-scripts.js
```

## Technical Stack

- WordPress users and roles
- WordPress authentication cookies
- WordPress AJAX API
- Nonces
- Posts, comments and metadata APIs
- `WP_Query`
- Shortcodes
- PHP
- jQuery / JavaScript
- CSS

## Installation

1. Download or clone this repository into `wp-content/plugins/`.
2. Activate **NewsPortal Manager** from WordPress.
3. Add `[my_custom_dashboard]` to the page intended to host the frontend dashboard.
4. Configure the surrounding WordPress pages/theme for the site's registration and news experience.

## Portfolio Note

This project demonstrates a multi-role frontend WordPress application rather than a conventional admin-only plugin: account creation, editorial workflow, content submission, moderation, notifications and analytics are coordinated through WordPress core APIs while exposing the operational workflow on the frontend.

## Author

**Sajjadur Rahaman Shawon**  
GitHub: https://github.com/shawonshajjad
