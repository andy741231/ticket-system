# Testing Guide: External User Authentication System

## Prerequisites

✅ All migrations completed successfully  
✅ Mail configuration set up in `.env`  
✅ Development server running (`php artisan serve` + `npm run dev`)

## Test Scenario 1: Make Image Public and Share

### Step 1: Create/View a Ticket with Annotation
1. Navigate to any ticket with an annotation image
2. Or create a new ticket and add an annotation image

### Step 2: Toggle Image to Public
1. In the annotation interface, find the **Public/Private toggle** (green toggle switch)
2. Click the toggle to make the image **Public**
3. Select an access level:
   - **View Only**: External users can only view
   - **Can Comment**: External users can comment on existing annotations
   - **Can Annotate**: External users can create annotations and comments (full access)

### Step 3: Copy Share Link
1. Click the **Share** button that appears when image is public
2. The link is copied to clipboard
3. Format: `http://localhost:8000/annotations/{image_id}/public`

## Test Scenario 2: External User Access Flow

### Step 1: Visit Public Link (Unauthenticated)
1. Open the share link in an incognito/private browser window
2. You should see:
   - The annotation image
   - "Public View" indicator
   - "Sign In to Collaborate" button
   - Message: "Sign in to add annotations and comments"

### Step 2: Request Verification
1. Click **"Sign In to Collaborate"** button
2. Enter your name and email in the modal
3. Click **"Send Verification Email"**
4. You should see: "Check Your Email!" success message

### Step 3: Verify Email
1. Check your email inbox
2. Open the verification email (subject: "Verify Your Email - Annotation Access")
3. Click the **"Verify Email & Access Annotation"** button
4. You should be redirected to the annotation page with authenticated session

### Step 4: Verify Authenticated State
After verification, you should see:
- Your name with a "Guest" badge in the header
- Full access to annotation tools (based on access level)
- Ability to create annotations and comments

## Test Scenario 3: Create Annotation as External User

### Step 1: Create Annotation
1. Select an annotation tool (rectangle, circle, text, etc.)
2. Draw on the image
3. Annotation should be created successfully
4. Your name should appear as the creator

### Step 2: Verify Permissions
1. Try to edit your own annotation - ✅ Should work
2. Try to delete your own annotation - ✅ Should work
3. Try to edit another user's annotation - ❌ Should be blocked

## Test Scenario 4: Mention System

### Step 1: Mention Another External User
1. Add a comment to an annotation
2. Type `@jane@example.com` in the comment
3. Submit the comment
4. Check that jane@example.com receives an invitation email

### Step 2: Mention an Internal User
1. Type `@internal.user@yourdomain.com` in a comment
2. Submit the comment
3. Internal user should receive a notification (if implemented)

### Step 3: Verify Autocomplete
1. Start typing `@` in a comment
2. Previously mentioned external users should appear in autocomplete
3. Select from the list or type full email

## Test Scenario 5: Session Management

### Step 1: Test Session Persistence
1. Close browser after verification
2. Reopen and visit the same public link
3. You should still be authenticated (session valid for 7 days)

### Step 2: Test Session Expiry
1. Manually clear cookies or wait 7 days
2. Visit the public link again
3. Should prompt for re-verification

### Step 3: Test Different Device/Browser
1. Copy the public link
2. Open in a different browser or device
3. Should prompt for verification (different fingerprint)

## Test Scenario 6: Access Level Restrictions

### View Only Mode
1. Set image to "View Only"
2. As external user, try to create annotation - ❌ Should show error
3. Try to add comment - ❌ Should show error

### Comment Mode
1. Set image to "Can Comment"
2. As external user, try to create annotation - ❌ Should show error
3. Try to add comment to existing annotation - ✅ Should work

### Annotate Mode (Full Access)
1. Set image to "Can Annotate"
2. As external user, try to create annotation - ✅ Should work
3. Try to add comment - ✅ Should work

## Test Scenario 7: Rate Limiting

1. Request verification 3 times in quick succession
2. On the 4th attempt, should see error: "Too many verification requests"
3. Wait 1 hour and try again - should work

## Expected Email Templates

### Verification Email
- **Subject**: "Verify Your Email - Annotation Access"
- **Content**: 
  - Greeting with user's name
  - Who invited them (if applicable)
  - Context of invitation
  - "Verify Email & Access Annotation" button
  - What they can do (list of capabilities)
  - 24-hour expiry warning

### Mention Notification Email
- **Subject**: "You were mentioned in an annotation comment"
- **Content**:
  - Who mentioned them
  - Preview of the comment
  - "View Comment & Respond" button
  - Session status warning if needed

## Troubleshooting

### Emails Not Sending
```bash
# Check mail configuration
php artisan tinker
Mail::raw('Test', function($m) { 
    $m->to('your@email.com')->subject('Test'); 
});

# Check logs
tail -f storage/logs/laravel.log
```

### Session Issues
```bash
# Check external user session
php artisan tinker
$user = App\Models\ExternalUser::where('email', 'test@example.com')->first();
$user->session_expires_at; // Should be in future
$user->session_token; // Should exist
```

### Database Verification
```bash
php artisan tinker

# Check external users
DB::table('external_users')->get();

# Check image access
DB::table('external_user_image_access')->get();

# Check public images
DB::table('ticket_images')->where('is_public', true)->get();
```

## Success Criteria

✅ External users can request and receive verification emails  
✅ Email verification creates valid 7-day session  
✅ External users can create annotations (based on access level)  
✅ External users can add comments (based on access level)  
✅ External users can only edit/delete their own content  
✅ Mention system sends invitations to new external users  
✅ Mention system sends notifications to existing external users  
✅ Public/private toggle works correctly  
✅ Access level restrictions are enforced  
✅ Share link copies to clipboard  
✅ Session persists across browser sessions  
✅ Rate limiting prevents spam  

## Next Steps After Testing

1. **Implement mention autocomplete** - Show external users in dropdown
2. **Add admin dashboard** - Manage external user access
3. **Implement access revocation** - UI for revoking access
4. **Add activity logs** - Track external user actions
5. **Complete internal user mentions** - Notification system for internal users
6. **Add email preferences** - Let users control notification frequency
7. **Implement cleanup job** - Remove expired sessions periodically

## Performance Monitoring

Monitor these metrics during testing:
- Email delivery time
- Session validation speed
- Annotation creation latency
- Database query performance
- Memory usage with multiple external users

## Security Checklist

✅ Session tokens are secure and random  
✅ Fingerprinting prevents session hijacking  
✅ Rate limiting prevents abuse  
✅ Email verification required for all access  
✅ Permissions properly enforced  
✅ Public tokens validated on every request  
✅ No sensitive data exposed in public views  
✅ CSRF protection enabled  
✅ SQL injection prevented (using Eloquent)  
✅ XSS protection (Vue escaping)
