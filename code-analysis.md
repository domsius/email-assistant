# Code Analysis: Email Style Tags Missing Issue

## Search Queries & Results

### Query 1: "class Email.*extends.*Model"
- Files found: None (Email model is actually named EmailMessage)
- Key patterns: Found EmailMessage model instead

### Query 2: "body_html"
- Files found: EmailSyncService.php, GmailService.php
- Key patterns: body_html is set in EmailSyncService but not provided by email providers

### Query 3: "extractBodyFromPayload"
- Files found: GmailService.php
- Key patterns: Method only extracts plain text, strips HTML tags

## Identified Patterns
- **Email Storage**: EmailMessage model stores both body_content (plain text) and body_html (HTML version)
- **Data Flow**: GmailService -> EmailSyncService -> EmailMessage model
- **Missing Data**: GmailService only returns body_content, not body_html

## Dependencies & References
- Direct dependencies: 
  - GmailService provides email data
  - EmailSyncService processes and stores emails
  - EmailMessage model defines data structure
- Files that reference this component:
  - Email sync commands
  - Email processing jobs
- Related services/models:
  - OutlookService (likely has same issue)
  - HtmlSanitizerService (may process HTML later)

## Root Cause
The GmailService's `extractBodyFromPayload` method:
1. Extracts HTML content from the email payload
2. Strips all HTML tags (including style tags) using `strip_tags()`
3. Returns only plain text as `body_content`
4. Never returns the HTML version, so `body_html` is always null

## Implementation Plan
1. Modify GmailService to return both plain text and HTML versions
2. Update the processEmail method return structure to include body_html
3. Ensure EmailSyncService properly passes body_html to EmailMessage
4. Test with emails containing style tags

## Changes Made

### GmailService.php
1. Modified `extractBodyFromPayload` method to return both plain and HTML content:
   - Changed return type from string to array with 'plain' and 'html' keys
   - Added recursive handling for multipart messages
   - Preserves original HTML content including style tags

2. Updated `processGmailMessage` to use the new return structure:
   - Changed `$body` to `$bodyData`
   - Added `'body_html' => $bodyData['html']` to the return array

### OutlookService.php
1. Modified `processOutlookMessage` to extract and preserve HTML content:
   - Added `$bodyHtml` variable
   - Conditionally sets HTML content when content type is 'html'
   - Added `'body_html' => $bodyHtml` to the return array

### HtmlSanitizerService.php
- Confirmed that the service already allows `<style>` tags and style attributes
- No changes needed here

## Consistency Checklist
- [x] Naming convention matches: body_html (consistent with model)
- [x] Follows architectural pattern: Service returns complete data
- [x] Error handling consistent with: Existing try-catch blocks
- [x] Test structure similar to: Other email sync tests

## Summary
The issue was that both email providers (Gmail and Outlook) were only returning plain text content, stripping HTML tags including style tags. The fix ensures that the original HTML content is preserved and stored in the `body_html` field, allowing email styles to be displayed correctly in the preview.