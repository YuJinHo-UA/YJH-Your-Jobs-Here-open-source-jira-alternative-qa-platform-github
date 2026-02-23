# BUG-2026-02-23-01: AI Chat Modal Looks Incorrect In Dark Theme

## Summary
In dark mode, the AI chat modal had a light message panel and mixed contrast, making the chat window visually inconsistent and uncomfortable to use.

## Environment
- Product: YJH
- Page: Any page with AI floating chat button
- Theme: Dark
- Browser: Reproducible in Chromium-based browsers

## Steps To Reproduce
1. Open YJH and switch to dark theme.
2. Click the floating AI chat button at bottom-right.
3. Observe modal background, messages area, and input contrast.

## Actual Result
- Message list area remained light.
- Modal body/header and text contrast were inconsistent in dark theme.

## Expected Result
- Full modal should follow dark theme palette.
- Message area, input, borders, and text should have readable contrast.

## Severity / Priority
- Severity: minor
- Priority: medium

## Attachment
- `assets/img/ai/ai-chat-attachment-15133509951240967712.png`

## Fix Implemented
- Removed inline light background from chat messages container.
- Added dedicated chat modal CSS for light/dark themes.
- Improved dark-mode contrast for message list, input, and modal surfaces.

## Changed Files
- `includes/footer.php`
- `assets/css/style.css`
