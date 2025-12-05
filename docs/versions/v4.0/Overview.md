# PressPrimer Quiz v4.0 Free - Context Overview

> **Purpose:** This is a context document for AI-assisted development. It provides high-level scope for v4.0 Free without detailed requirements. Detailed specifications will be created closer to development.

> **Target Release:** 6 months after v3.0 launch (12 months after v1.0)  
> **Coincides With:** Premium addon tier 3.0 releases

---

## Version Theme

**"Analytics, Insights & Platform Maturity"**

Version 4.0 brings the free plugin to full platform maturity with advanced analytics, comprehensive reporting, and quality-of-life improvements that cement PressPrimer Quiz as the professional choice for WordPress-based assessment. This version focuses on helping educators understand learning outcomes, not just scores.

---

## New Free Features in v4.0

### Analytics Dashboard
- **Visual Dashboard:** React-based analytics overview page
- **Score Distribution Charts:** Histogram of quiz scores across all attempts
- **Time Analysis:** Average completion time, time per question
- **Trend Visualization:** Performance over time for groups and quizzes

### Enhanced Pre/Post Test Analysis
- **Per-Question Gain:** Which questions showed most/least improvement
- **Group Comparison:** Compare pre/post gains between groups
- **Visual Comparison:** Side-by-side bar charts, improvement highlighting

### User Experience Polish
- **Keyboard Navigation:** Full keyboard accessibility in quiz builder
- **Bulk Operations:** Select multiple questions for batch edit/delete/move

### Performance & Scale
- **Database Optimization:** Query improvements for large datasets
- **Caching Layer:** Result caching for dashboard performance
- **Background Processing:** Long-running reports process asynchronously
- **Data Archival:** Option to archive old attempts to reduce database size

### Status & Repair Tools
- **Data Integrity Check:** Scan for orphaned records, broken references
- **Repair Wizard:** Fix common data issues automatically
- **Debug Mode:** Enhanced logging for troubleshooting
- **System Status Page:** PHP version, database status, integration health

---

## Features NOT in v4.0 Free (Premium Tier)

These features require premium addons:

| Feature | Premium Tier |
|---------|--------------|
| Spaced repetition automation | Educator |
| CSV/JSON import/export | Educator |
| Question performance analysis | Institution |
| PDF report export | Institution |
| Scheduled reports | Institution |
| Statistical significance analysis | Institution |
| Advanced psychometric analysis | Institution |
| LRS/xAPI advanced analytics | Institution |
| Kirkpatrick Level 2/3 assessment tools | Enterprise |
| Compliance audit reports | Enterprise |
| Custom report builder | Enterprise |
| White-label/custom branding | Enterprise |
| API access for external systems | Institution |

---

## Database Implications for v3.0

v3.0 must include database structures to support v4.0 features:

### New Tables for v4.0
```sql
-- Cached analytics (computed asynchronously)
ppq_analytics_cache (
    id BIGINT PRIMARY KEY,
    entity_type ENUM('quiz', 'question', 'group'),
    entity_id BIGINT,
    metric_type VARCHAR(50),
    metric_value JSON,
    computed_at DATETIME,
    expires_at DATETIME,
    INDEX (entity_type, entity_id),
    INDEX (expires_at)
)

-- Archived attempts (for data management)
ppq_attempts_archive (
    -- Same structure as ppq_attempts
    -- Used for old data moved out of main table
)
```

---

## Hooks to Expose in v3.0 (for v4.0)

```php
// Analytics computation hooks
do_action('pressprimer_quiz_analytics_computed', $entity_type, $entity_id, $metrics);
apply_filters('pressprimer_quiz_analytics_metrics', $metrics, $entity_type, $entity_id);

// Data management hooks
do_action('pressprimer_quiz_attempts_archived', $archived_count, $cutoff_date);
do_action('pressprimer_quiz_integrity_check_completed', $issues_found, $issues_fixed);

// Dashboard hooks
apply_filters('pressprimer_quiz_dashboard_widgets', $widgets, $user_id);
apply_filters('pressprimer_quiz_dashboard_date_range', $start, $end);
```

---

## UI Changes from v3.0

### New Admin Screens
- **Analytics Dashboard:** Top-level "Analytics" submenu
  - Overview tab (key metrics, trends)
  - Quiz Performance tab
  - Group Comparison tab
- **System Status:** Health checks and repair tools

### Existing Screen Updates
- Quiz list: Add "View Analytics" quick action
- Group list: Add performance summary column
- Settings: Add archival and caching options

### Dashboard Widgets
- "Quiz Performance Overview" widget
- "Recent Activity" widget

---

## Success Metrics for v4.0

- Analytics dashboard daily active usage
- System status tool usage (indicates trust in data quality)
- Support ticket reduction (better self-service tools)
- Premium conversion from analytics power users

---

## Development Notes

- Analytics computations must be background jobs (not real-time)
- Dashboard must load quickly even with large datasets
- Data archival must be reversible (restore from archive)
- System status checks must not lock tables or impact performance

---

## Architecture Considerations

### Performance
- Analytics cache table is critical - queries hit cache first
- Background processing queue for analytics computation
- Consider WebSocket for real-time dashboard updates (or polling)
- Database indexing strategy for large attempt volumes

### Scalability
- Analytics computations should be incremental where possible
- Archive table can be on separate database/server if needed

### Security
- Analytics data must respect quiz/group visibility rules
- Debug mode must not expose sensitive data

### Data Integrity
- Archival process must be transactional
- Analytics cache must invalidate correctly on data changes
- Repair tool must log all changes for audit trail
- Integrity checks must be non-destructive

---

## Migration Considerations

### From v3.0 to v4.0
- Initial analytics computation runs as background process
- Existing data unchanged; analytics are additive
- No breaking changes to existing workflows

### First Run
- Analytics cache populated asynchronously (may take hours for large sites)
- Dashboard shows "Computing..." state during initial analysis

### Performance
- Sites with 100K+ attempts need careful cache warming
- Consider "quick stats" vs "full analytics" modes
- Admin notification when analytics computation completes

---

## Chart Library Considerations

For the analytics dashboard visualization, evaluate:

| Library | Pros | Cons |
|---------|------|------|
| Chart.js | Lightweight, familiar | Limited customization |
| Recharts | React-native, composable | Larger bundle |
| ApexCharts | Beautiful defaults | Commercial license complexity |
| Victory | Accessible, flexible | Steeper learning curve |

**Recommendation:** Recharts for React consistency with existing admin interfaces (quiz builder, question builder).

---

## Long-Term Considerations

v4.0 represents the mature free product. Beyond v4.0:

- Free plugin focuses on stability, security, compatibility
- New features primarily in premium tiers
- Free tier may receive learning science improvements from premium R&D
- Consider "LTS" versioning for enterprise stability needs
- Plan for WordPress evolution (FSE, blocks, performance features)
