# Backend Analysis: Draft Folder Loading Error

## Issue Summary
The user is experiencing a "Failed to load emails. Please try again." error when accessing the draft folder. The root cause is a missing database column `is_deleted` in the `email_drafts` table.

## Error Details
From the Laravel logs:
```
[2025-07-28 11:20:16] local.ERROR: Failed to load inbox {"user_id":2,"error":"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'is_deleted' in 'where clause' (Connection: mysql, SQL: select count(*) as aggregate from `email_drafts` where `user_id` = 2 and `is_deleted` = 0 and `email_account_id` in (25))"}
```

## Root Cause Analysis

### 1. Migration Not Run
- A migration file exists: `2025_07_28_111235_add_soft_delete_to_email_drafts_table.php`
- This migration adds:
  - `is_deleted` boolean column (default: false)
  - `deleted_at` timestamp column (nullable)
  - Composite index on `[user_id, is_deleted]` for performance

### 2. Code Already Updated
- The `EmailService::getDraftEmails()` method (line 99) is already using:
  ```php
  $query = EmailDraft::where('user_id', $user->id)
      ->where('is_deleted', false)
      ->with(['emailAccount', 'originalEmail']);
  ```
- This query expects the `is_deleted` column to exist

### 3. Error Handling
- The `InboxController` catches the exception and logs it
- Returns an empty response to the frontend instead of showing database errors
- Frontend displays generic "Failed to load emails" message

## Solution
The user needs to run the migration to add the missing column:

```bash
docker compose exec app php artisan migrate
```

This will:
1. Add the `is_deleted` column to the `email_drafts` table
2. Add the `deleted_at` column for soft delete timestamps
3. Create the performance index
4. Allow the draft emails query to execute successfully

## Architecture Notes

### Service Layer Pattern
- `EmailService` encapsulates email-related business logic
- Private method `getDraftEmails()` handles draft-specific queries
- Follows multi-tenancy pattern by filtering on `company_id` through email accounts

### Error Handling Pattern
- Controllers catch exceptions and log them with context
- Graceful degradation - returns empty data instead of error pages
- Frontend shows user-friendly error messages

### Soft Delete Implementation
- Using custom soft delete pattern with `is_deleted` flag
- Maintains `deleted_at` timestamp for audit trail
- All queries filter out deleted drafts by default

## Prevention Recommendations
1. Consider adding migration status checks in health endpoints
2. Implement database schema validation on startup
3. Add more specific error messages for missing columns vs other database errors