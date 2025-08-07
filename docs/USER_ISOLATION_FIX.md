# User Isolation Fix Documentation

## Problem Statement
After implementing strict user-level isolation (removing `orWhereNull('user_id')` clauses), new users couldn't see or create email accounts because:
1. Existing email accounts had `user_id = NULL`
2. New users might not have a `company_id` assigned
3. The system enforced strict isolation where users could only see accounts with their specific `user_id`

## Solution Implemented

### 1. Database Migration
**File:** `database/migrations/2025_08_07_081444_fix_email_accounts_user_id_nullable.php`

This migration:
- Finds all email accounts with `NULL` user_id
- Assigns them to the first available user in the same company
- Deletes orphaned accounts if no users exist in the company

### 2. Command Line Tool
**File:** `app/Console/Commands/FixOrphanedEmailAccounts.php`

Usage:
```bash
# Check what would be fixed (dry run)
php artisan email:fix-orphaned --dry-run

# Fix orphaned accounts by assigning them
php artisan email:fix-orphaned

# Delete orphaned accounts instead of assigning
php artisan email:fix-orphaned --delete
```

The command uses a smart assignment strategy:
1. First tries to assign to an admin user in the company
2. Falls back to any active user in the company
3. Finally assigns to any user in the company
4. Creates a default admin user if no users exist

### 3. Model Updates
**File:** `app/Models/EmailAccount.php`

Added:
- `user_id` to fillable array
- `user()` relationship method

### 4. Registration Flow
**File:** `app/Http/Controllers/Auth/RegisteredUserController.php`

Already handles:
- Creates a company for new users
- Assigns company_id to the user
- Sets the user as admin of their company

## Prevention Measures

### For New Email Accounts
All controllers now ensure `user_id` is set when creating email accounts:
- `EmailAccountController::connect()` - Sets `user_id` when creating accounts
- `Api\EmailAccountController::initiateOAuth()` - Sets `user_id` in API flow

### For Existing Accounts
- Migration assigns orphaned accounts to appropriate users
- Command available for manual cleanup if needed

## Verification

To verify the fix is working:
```bash
# Check for orphaned accounts
docker exec app php artisan email:fix-orphaned --dry-run

# Check database directly
docker exec app php -r "
\$pdo = new PDO('mysql:host=db;dbname=email_saas', 'root', 'email_saas');
\$stmt = \$pdo->query('SELECT COUNT(*) as orphaned FROM email_accounts WHERE user_id IS NULL');
var_dump(\$stmt->fetch(PDO::FETCH_ASSOC));
"
```

## Security Benefits
- Complete user isolation - users can only see their own data
- No shared email accounts between users
- Admin users don't have special access to other users' data
- Prevents data leakage between tenants in multi-tenant SaaS

## Rollback Plan
If issues occur:
1. Restore the `orWhereNull('user_id')` clauses in:
   - `app/Services/EmailService.php`
   - `app/Repositories/EmailRepository.php`
2. This will allow users to see unassigned accounts again
3. Fix any data issues before re-applying strict isolation