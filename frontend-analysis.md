# Frontend Analysis for Draft Preview Feature

## Current Implementation

### Draft Handling Flow
1. **Current Behavior**: When a draft is selected, it immediately opens in compose mode (based on `setSelectedEmail` in inbox-context.tsx line 127-128)
2. **Email Preview**: The `EmailPreview` component already has support for drafts with an "Edit Draft" button (line 222-225 in email-preview.tsx)
3. **Compose Panel**: Already supports draft action type and can handle draft data

### Key Components

#### InboxContext (`resources/js/contexts/inbox-context.tsx`)
- `setSelectedEmail`: Currently returns early for drafts (line 127-128) to prevent preview
- `enterComposeMode`: Function to switch to compose mode with data
- `ComposeData` interface: Supports draft action and draftId

#### EmailPreview (`resources/js/components/ui/email-preview.tsx`)
- Already has `isDraft` prop and `onEditDraft` callback
- Shows "Edit Draft" button for drafts instead of reply buttons

#### ComposePanel (`resources/js/components/inbox/compose-panel.tsx`)
- Supports `originalEmail` prop to show quoted content
- Already handles draft action type
- Has auto-save functionality for drafts

#### InboxContent (`resources/js/components/inbox/inbox-content.tsx`)
- Has `handleEditDraft` function that fetches draft content and enters compose mode
- Already passes `onEditDraft` to EmailPreview component

## Implementation Plan

### 1. Remove Automatic Draft Opening
- Need to find and remove the useEffect that automatically opens drafts in compose mode
- This logic might be in inbox.tsx or elsewhere

### 2. Allow Draft Preview in InboxContext
- Modify `setSelectedEmail` to NOT return early for drafts
- Let drafts be displayed in the preview panel like regular emails

### 3. Update ComposePanel for Draft Display
- When action is "draft", show the original draft content below the compose area
- Similar to how replies show the original email content
- Use the same quoted styling as replies

### 4. Ensure Proper Data Flow
- Draft content should be fetched and displayed in preview
- "Edit Draft" button should trigger `handleEditDraft` 
- `handleEditDraft` should enter compose mode with draft shown as quoted content

## Search Findings
- No automatic draft opening logic found in inbox.tsx
- The early return in `setSelectedEmail` (line 127-128) prevents drafts from being shown
- All necessary components already exist and support drafts

## Next Steps
1. Remove the early return for drafts in `setSelectedEmail`
2. Modify ComposePanel to show draft content when action is "draft"
3. Test the flow to ensure drafts preview correctly