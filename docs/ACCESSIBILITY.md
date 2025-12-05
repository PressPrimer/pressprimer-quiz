# Accessibility Documentation

PressPrimer Quiz is designed with accessibility in mind, following WCAG 2.1 AA guidelines.

## Implemented Features

### Keyboard Navigation
- All interactive elements (buttons, answer options, form fields) are keyboard accessible
- Arrow keys navigate between questions (Left/Right)
- Tab key moves through focusable elements
- Enter/Space activates buttons and selects answers
- Escape key dismisses modals and warnings

### Screen Reader Support
- **Live Regions**: Timer warnings, save status, and question changes are announced
- **ARIA Labels**: All buttons, navigation elements, and dynamic content have descriptive labels
- **Semantic HTML**: Proper heading hierarchy, landmarks (main, navigation), and form labels
- **Hidden Decorative Elements**: Icons and decorative elements use `aria-hidden="true"`

### Visual Accessibility
- **Color Contrast**: All text meets WCAG 2.1 AA minimum contrast ratio (4.5:1)
  - Primary colors adjusted for accessibility
  - Success: #059669 (5.4:1 contrast)
  - Error: #dc2626 (5.0:1 contrast)
  - Warning: #d97706 (4.5:1 contrast)
  - Info: #2563eb (5.1:1 contrast)
- **Focus Indicators**: Visible focus rings on all interactive elements
- **High Contrast Mode**: Additional borders in high contrast mode
- **Reduced Motion**: Respects `prefers-reduced-motion` media query

### RTL (Right-to-Left) Language Support
- Full RTL support for Arabic, Hebrew, Farsi, and other RTL languages
- Navigation, buttons, and layout elements properly mirrored
- Progress bars and category charts fill from right to left
- Alert borders positioned on the right side

### Skip Links
- "Skip to questions" link for keyboard users to bypass header content

### Timer Accessibility
- 5-minute warning announced to screen readers
- 1-minute warning announced (assertive) to screen readers
- Timer uses `role="timer"` with appropriate ARIA attributes
- Visual pulsing animation for warnings (respects reduced motion)

## Known Limitations

1. **Quiz Timer**: The countdown timer does not continuously announce remaining time to avoid being disruptive. Users are alerted at 5-minute and 1-minute marks only.

2. **Drag-and-Drop**: Question reordering in the admin uses drag-and-drop which has limited keyboard support. Alternative keyboard controls are planned for future versions.

3. **Complex Tables**: Report tables in the admin area use standard HTML tables. Screen reader navigation may require familiarity with table navigation commands.

4. **PDF/Word Upload**: AI generation file upload interface relies on standard file input. Users may need to navigate to the file input using screen reader commands.

5. **Third-Party LMS Integration**: When integrated with LearnDash, TutorLMS, or LifterLMS, accessibility depends on the LMS theme's implementation.

## Testing Performed

- Manual testing with VoiceOver (macOS)
- Keyboard-only navigation testing
- Color contrast verification using WebAIM Contrast Checker
- Focus management verification

## Reporting Issues

If you encounter accessibility barriers while using PressPrimer Quiz, please report them via:
- GitHub Issues: [pressprimer-quiz/issues](https://github.com/pressprimer/pressprimer-quiz/issues)
- Include your screen reader/assistive technology and browser version

## Future Improvements

- Enhanced keyboard controls for drag-and-drop operations
- ARIA live region for progress bar updates
- Improved table navigation in reports
- Voice control compatibility testing
