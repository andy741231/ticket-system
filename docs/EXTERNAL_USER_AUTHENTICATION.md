# External User Authentication System for Annotations

## Overview

This document describes the external user authentication system implemented for the annotation feature. External users can access public annotations, add their own annotations and comments, and mention other users (both internal and external) via email.

## Key Features

### 1. **Email-Based Authentication**
- No password required
- Email verification for each access
- Session management with 7-day expiry
- Fingerprint-based security (IP + User-Agent)

### 2. **Public/Private Annotations**
- Images can be toggled between public and private
- Three access levels for public images:
  - `view_only`: Can only view annotations
  - `comment`: Can view and comment on existing annotations
  - `annotate`: Full access (create annotations + comments)

### 3. **Email-Based Mentions**
- Mention format: `@email@domain.com`
- Works for both internal users and external users
- Autocomplete shows previously verified external users
- Automatic invitation emails for new mentions

### 4. **Permission System**
- External users can only edit/delete their own content
- Same permissions as regular users for their own content
- Cannot edit/delete content from internal users or other external users

## Database Schema

### New Tables

#### `external_users`
```sql
- id
- email (unique)
- name
- verification_token
- verified_at
- session_token
- session_expires_at
- session_fingerprint
- last_activity_at
- timestamps
```

#### `external_user_image_access`
```sql
- id
- external_user_id (FK)
- ticket_image_id (FK)
- invited_by_user_id (FK to users, nullable)
- invited_at
- first_accessed_at
- last_accessed_at
- access_revoked (boolean)
- timestamps
```

### Modified Tables

#### `ticket_images`
- Added: `is_public` (boolean, default false)
- Added: `public_access_level` (enum: view_only, comment, annotate)

#### `annotations`
- Added: `external_user_id` (FK, nullable)

#### `annotation_comments`
- Added: `external_user_id` (FK, nullable)

## Authentication Flow

### First-Time Access

1. User visits public annotation link
2. Prompted for name and email
3. System sends verification email
4. User clicks verification link
5. Session created (7 days)
6. Redirected to annotation page

### Returning Access

1. User visits public annotation link
2. System checks for valid session cookie
3. If valid: Access granted
4. If invalid/expired: Request new verification email

### Mention Flow

1. User mentions `@jane@example.com` in comment
2. System checks if email exists in database
3. If new: Create external user, send invitation
4. If existing with active session: Send notification
5. If existing without session: Send verification email
6. Email includes direct link to the annotation/comment

## API Endpoints

### External Authentication
- `POST /external-auth/annotations/{image}/request-verification` - Request verification email
- `GET /external-auth/annotations/{image}/verify` - Verify email and create session
- `GET /external-auth/annotations/{image}/check-session` - Check current session status
- `POST /external-auth/logout` - Logout and invalidate session

### Public Annotations (requires session for write operations)
- `GET /api/public/annotations/{image}` - List annotations
- `POST /api/public/annotations/{image}` - Create annotation (auth required)
- `DELETE /api/public/annotations/{image}/{annotation}` - Delete own annotation (auth required)

### Public Comments (requires session for write operations)
- `GET /api/public/annotations/{image}/annotations/{annotation}/comments` - List comments
- `POST /api/public/annotations/{image}/annotations/{annotation}/comments` - Add comment (auth required)
- `PUT /api/public/annotations/{image}/annotations/{annotation}/comments/{comment}` - Update own comment (auth required)
- `DELETE /api/public/annotations/{image}/annotations/{annotation}/comments/{comment}` - Delete own comment (auth required)

## Frontend Components

### `ExternalUserLoginModal.vue`
- Modal for email verification request
- Name and email input
- Success state with instructions
- Error handling

### Updated `Annotations/Show.vue`
- External user session check on mount
- Login modal integration
- Permission checks for external users
- User display in header (shows "Guest" badge)
- Authentication prompts for unauthenticated actions

## Email Notifications

### `ExternalUserVerification`
- Sent when user requests access or is mentioned
- Contains verification link
- Explains what user can do
- 24-hour expiry warning

### `ExternalUserMentionNotification`
- Sent when external user is mentioned
- Shows comment preview
- Direct link to annotation/comment
- Session status warning if needed

## Security Features

### 1. **Session Fingerprinting**
- Combines IP address and User-Agent
- Prevents session hijacking
- Requires re-verification if fingerprint changes

### 2. **Rate Limiting**
- Max 3 verification requests per hour per email
- Prevents spam and abuse

### 3. **Token Validation**
- Public image tokens validated on every request
- Verification tokens are single-use
- Session tokens expire after 7 days

### 4. **Access Control**
- External users can only access images they're invited to
- Access can be revoked by admins
- Audit trail of all access (first/last accessed times)

## Usage Instructions

### For Internal Users (Sharing Annotations)

1. Upload/create annotation on a ticket
2. Toggle image to "Public" (future feature)
3. Set access level (view/comment/annotate)
4. Share the public link
5. Mention external users in comments using `@email@domain.com`

### For External Users (Accessing Annotations)

1. Receive link from internal user or mention notification
2. Enter name and email when prompted
3. Check email for verification link
4. Click link to access annotation
5. Add annotations/comments as permitted
6. Mention others using `@email@domain.com`

## Next Steps (Pending Implementation)

1. **Public/Private Toggle UI** - Add toggle in AnnotationInterface.vue
2. **Mention Autocomplete** - Show external users in mention dropdown
3. **Admin Dashboard** - Manage external user access
4. **Access Revocation** - UI for revoking external user access
5. **Activity Logs** - Track external user actions
6. **Internal User Mentions** - Complete notification system for internal users

## Testing Checklist

- [ ] Run migrations: `php artisan migrate`
- [ ] Test email verification flow
- [ ] Test session creation and validation
- [ ] Test annotation creation as external user
- [ ] Test comment creation as external user
- [ ] Test mention system (@email format)
- [ ] Test permission checks (edit/delete own content only)
- [ ] Test session expiry and re-verification
- [ ] Test rate limiting on verification requests
- [ ] Test fingerprint validation
- [ ] Test access revocation
- [ ] Test email notifications (verification, mentions)

## Configuration

### Environment Variables
Ensure these are set in `.env`:
```
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Session Configuration
External user sessions use HTTP-only cookies:
- `external_session` - Session token (7 days)
- `external_user_id` - User ID for quick lookup (7 days)

## Troubleshooting

### External user can't create annotations
- Check if image is public: `$image->isPublic()`
- Check access level: `$image->canExternalUsersAnnotate()`
- Verify session is valid: Check cookies and fingerprint

### Verification emails not sending
- Check mail configuration in `.env`
- Check mail logs: `storage/logs/laravel.log`
- Test mail with: `php artisan tinker` → `Mail::raw('Test', function($m) { $m->to('test@example.com')->subject('Test'); })`

### Session expires too quickly
- Check `session_expires_at` in database
- Verify cookie expiry matches (7 days)
- Check if fingerprint is changing (proxy/VPN issues)

### Mentions not working
- Check regex pattern in `extractMentions()` method
- Verify email format: `@email@domain.com`
- Check mail queue: `php artisan queue:work`

## Performance Considerations

- External user sessions are validated on every API request
- Consider caching session validation results
- Use queue for sending mention notification emails
- Index `external_user_id` columns for faster queries
- Clean up expired sessions periodically

## Future Enhancements

1. **OAuth Integration** - Allow external users to sign in with Google/Microsoft
2. **Two-Factor Authentication** - Optional 2FA for sensitive annotations
3. **Bulk Invitations** - Invite multiple external users at once
4. **Access Templates** - Predefined access levels for different user groups
5. **Notification Preferences** - Let external users control email frequency
6. **Mobile App Support** - Extend authentication to mobile apps
7. **SSO Integration** - Support for enterprise SSO providers
