# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is an AI-powered email management SaaS built with Laravel (backend) and React/TypeScript (frontend) using the Inertia.js framework for seamless SPA functionality.

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: React 19, TypeScript, Vite
- **UI Framework**: shadcn/ui v4 components with Tailwind CSS
- **Database**: SQLite (development), supports MySQL/PostgreSQL
- **Email Providers**: Gmail (OAuth2), Outlook/Microsoft Graph (OAuth2)
- **AI Integration**: OpenAI API for email analysis and response generation
- **Search**: Elasticsearch for full-text search and RAG functionality
- **Queue**: Laravel Queue for background processing

## Development Commands

### Backend
```bash
# Start all services (server, queue, logs, vite)
composer dev

# Start with SSR
composer dev:ssr

# Run tests
composer test
php artisan test
php artisan test --filter=SpecificTest

# Code quality
./vendor/bin/pint          # Laravel code style fixer
./vendor/bin/phpstan        # Static analysis

# Database
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=TestDataSeeder

# Queue/Jobs
php artisan queue:work
php artisan queue:listen --tries=1

# Email sync
php artisan sync:emails {accountId}
php artisan sync:emails:all
```

### Frontend
```bash
# Development
npm run dev

# Build
npm run build
npm run build:ssr

# Code quality
npm run lint               # ESLint with auto-fix
npm run format            # Prettier formatting
npm run format:check      # Check formatting
npm run types             # TypeScript type checking
```

## Architecture

### Backend Structure

The application follows a layered architecture pattern:

1. **Controllers** (`app/Http/Controllers/`) - Handle HTTP requests, validate input, return responses
   - API controllers for external integrations
   - Web controllers for Inertia.js pages
   - Settings controllers for user preferences

2. **Services** (`app/Services/`) - Business logic layer
   - `EmailService` - Core email operations (CRUD, folder management)
   - `EmailSyncService` - Synchronizes emails from providers
   - `EmailProviderInterface` - Contract for email providers (Gmail, Outlook)
   - `AIResponseService` - Generates AI responses using OpenAI
   - `EmailProcessingService` - Processes emails for AI analysis
   - `HtmlSanitizerService` - Sanitizes email HTML content
   - `ElasticsearchService` - Manages search functionality
   - `RAGService` - Retrieval-Augmented Generation for contextual responses

3. **Repositories** (`app/Repositories/`) - Data access layer
   - `EmailRepository` - Handles email queries with optimized pagination

4. **Models** (`app/Models/`) - Eloquent ORM models
   - Multi-tenancy via `company_id` on most models
   - Encrypted sensitive data (OAuth tokens, email content)

5. **Jobs** (`app/Jobs/`) - Background processing
   - `SyncEmailAccountJob` - Syncs emails for an account
   - `ProcessIncomingEmail` - Processes individual emails
   - `ProcessDocument` - Processes uploaded documents for RAG

### Frontend Structure

React application using Inertia.js for server-side routing:

1. **Pages** (`resources/js/pages/`) - Top-level route components
   - Each page receives props from Laravel controllers
   - Uses layouts for consistent structure

2. **Components** (`resources/js/components/`)
   - `ui/` - Reusable shadcn/ui components
   - `inbox/` - Email-specific components
   - Context providers for state management

3. **Layouts** (`resources/js/layouts/`)
   - `AppLayout` - Main authenticated layout with sidebar
   - `AuthLayout` - Layout for login/register pages

4. **Contexts** (`resources/js/contexts/`)
   - `InboxContext` - Manages email list state and operations

### Key Design Patterns

1. **Repository Pattern** - Abstracts database queries
2. **Service Layer** - Encapsulates business logic
3. **Provider Pattern** - Email provider abstraction
4. **DTO Pattern** - Data transfer objects for API responses
5. **Multi-tenancy** - Company-based data isolation
6. **Encrypted Attributes** - Laravel's encrypted casting for sensitive data

## Email Processing Architecture

### Storage Modes
- **Full Mode**: Stores complete email content in database
- **Metadata Mode**: Stores only metadata, fetches content on-demand (GDPR compliant)

### Processing Pipeline
1. Email sync triggered (manual or scheduled)
2. Fetch emails from provider API
3. Store/update in database
4. Queue processing jobs:
   - Language detection
   - Topic classification
   - Sentiment analysis
   - AI summary generation

### Security Features
- OAuth2 authentication for email providers
- Encrypted storage of tokens and sensitive data
- HTML sanitization with configurable rules
- Multi-level content filtering (backend + frontend)

## AI Integration

### OpenAI Features
- Email summarization
- Key points extraction
- Suggested responses
- Sentiment analysis
- Urgency detection

### RAG (Retrieval-Augmented Generation)
- Document upload and processing
- Chunk generation with embeddings
- Context-aware response generation
- Elasticsearch vector search integration

## Development Tips

### Working with Inertia.js
- Props passed from controllers are available in page components
- Use `router.visit()` for navigation
- Partial reloads with `only: ['prop1', 'prop2']`
- Form handling with `useForm` hook

### Email Preview Styling
- Backend sanitization in `HtmlSanitizerService`
- Frontend sanitization with DOMPurify
- Scoped CSS to prevent style bleeding
- Support for legacy email HTML attributes

### Testing Email Sync
1. Connect email account via `/email-accounts`
2. Use sync button or run `php artisan sync:emails {accountId}`
3. Check logs with `php artisan pail`
4. Monitor queue with `php artisan queue:listen`

### Environment Variables
Key variables to configure:
- `EMAIL_STORAGE_MODE` - 'full' or 'metadata'
- `OPENAI_API_KEY` - For AI features
- `GOOGLE_CLIENT_ID/SECRET` - Gmail OAuth
- `MICROSOFT_CLIENT_ID/SECRET` - Outlook OAuth
- `ELASTICSEARCH_HOST` - Search functionality

## Common Tasks

### Adding a New Email Provider
1. Create service implementing `EmailProviderInterface`
2. Register in `EmailProviderFactory`
3. Add OAuth configuration in `config/services.php`
4. Create connection flow in `EmailAccountController`

### Adding UI Components
1. Check shadcn/ui documentation first
2. Components are in `resources/js/components/ui/`
3. Follow existing patterns for consistency
4. Use Tailwind CSS classes for styling

### Debugging
- Laravel logs: `storage/logs/laravel.log`
- Live logs: `php artisan pail`
- Queue failures: Check `failed_jobs` table
- Browser console for frontend errors
- React Developer Tools for component state

## Docker Execution

- **PHP Artisan Commands**: You MUST use `docker exec app command` in order to run php artisan commands

## Important Development Notes

- You MUST not run the npm run build and npm run commands, because im running these in other terminals.

# important-instruction-reminders
Do what has been asked; nothing more, nothing less.
NEVER create files unless they're absolutely necessary for achieving your goal.
ALWAYS prefer editing an existing file to creating a new one.
NEVER proactively create documentation files (*.md) or README files. Only create documentation files if explicitly requested by the User.