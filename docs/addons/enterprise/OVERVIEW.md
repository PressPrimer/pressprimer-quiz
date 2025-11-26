# PressPrimer Quiz - Enterprise Addon

> **Purpose:** Context document for AI-assisted development. Provides scope for the Enterprise premium tier across versions.

> **Price:** $399/year (up to 5 site licenses)  
> **Target Market:** Large universities, Fortune 500 training departments, government agencies, multi-site organizations

---

## Addon Philosophy

The Enterprise tier unlocks capabilities for organizations requiring security, compliance, branding control, and advanced assessment science. Features focus on proctoring, audit trails, white-labeling, and enterprise-grade integrations.

**Value Proposition:** *Enterprise capabilities at a fraction of enterprise costs. No per-user fees means unlimited scalability.*

---

## Includes All School Features

Enterprise tier includes everything in Educator and School tiers:

**From Educator:**
- Survey & ungraded questions
- Confidence ratings
- Branching & conditional logic
- Front-end quiz authoring
- LaTeX math support
- Import/Export
- AI distractor generation
- Spaced repetition tools

**From School:**
- Advanced pre/post analysis
- Progress over attempts
- Observational assessments
- xAPI/LRS integration
- Multi-teacher coordination
- Department reporting
- Nested group hierarchies
- Question performance analysis
- PDF reports & scheduled reports
- SSO/SAML integration

---

## Version 1.0 Features (Launches with Free v2.0)

### Proctoring (Basic)
- **Browser Lockdown:** Prevent tab switching, copy/paste
- **Fullscreen Enforcement:** Require fullscreen mode during quiz
- **Time Monitoring:** Flag unusual time patterns (too fast, long pauses)
- **Attempt Logging:** Detailed log of all student actions
- **Violation Alerts:** Notify teachers of suspicious behavior
- **Proctoring Report:** Summary of flagged attempts per quiz

### Audit Logging
- **Comprehensive Logs:** Every admin action recorded
- **User Action Tracking:** Who did what, when
- **Data Change History:** Before/after values for edits
- **Log Retention:** Configurable retention period
- **Log Export:** Download logs for compliance
- **Tamper Protection:** Logs cannot be modified

### White-Label (Basic)
- **Remove All Branding:** No PressPrimer references anywhere
- **Custom Plugin Name:** Rename in WordPress admin
- **Custom Admin Colors:** Match organizational branding
- **Custom Email Templates:** Branded notification emails
- **Logo Replacement:** Organization logo in all interfaces

### Multi-Site License Management
- **Centralized Dashboard:** Manage all 5 sites from one view
- **License Transfer:** Move licenses between sites
- **Usage Reporting:** Activity across all licensed sites
- **Bulk Updates:** Push settings to multiple sites

### API Access (Basic)
- **REST API:** Programmatic access to quiz data
- **API Key Management:** Generate and revoke keys
- **Rate Limiting:** Configurable request limits
- **Webhook Support:** Real-time event notifications
- **API Documentation:** Developer reference included

---

## Version 2.0 Features (Launches with Free v3.0)

### Advanced Proctoring
- **Webcam Monitoring:** Capture periodic photos during quiz
- **Photo Review Interface:** Review captured images
- **AI Photo Analysis:** Detect multiple faces, phone use
- **Screen Recording:** Optional full session recording
- **Identity Verification:** Photo ID comparison at start
- **Proctoring Dashboard:** Real-time monitoring of active sessions

### Kirkpatrick Assessment Tools
- **Level 1 - Reaction:** Post-quiz satisfaction surveys
- **Level 2 - Learning:** Quiz-based knowledge measurement
- **Level 3 - Behavior:** Follow-up application assessments
- **Level 4 - Results:** Link to business outcome metrics
- **Kirkpatrick Reports:** Evaluation summaries by level
- **ROI Calculation:** Training investment analysis

### Custom Report Builder
- **Drag-Drop Report Designer:** Build custom report layouts
- **Data Field Library:** All available metrics as building blocks
- **Calculated Fields:** Custom formulas and aggregations
- **Conditional Formatting:** Highlight based on thresholds
- **Template Saving:** Reuse custom report designs
- **SQL Access:** Direct query for power users (read-only)

### Compliance Features
- **FERPA Compliance:** Student data protection tools
- **GDPR Support:** Data export, deletion requests
- **Data Retention Policies:** Auto-delete aged data
- **Consent Management:** Track user consent
- **Privacy Impact Reports:** Generate compliance documentation
- **Encryption Options:** Field-level encryption for sensitive data

### Advanced Integrations
- **HRIS Connectors:** Sync with HR systems (Workday, SAP, etc.)
- **BI Tool Export:** Formatted data for Tableau, Power BI
- **Custom Webhooks:** Configure advanced webhook payloads
- **LTI 1.3 Support:** Learning Tools Interoperability
- **SCORM Package Export:** Package quizzes as SCORM modules

---

## Version 3.0 Features (Launches with Free v4.0)

### Multi-Tenant Deployment
- **Tenant Isolation:** Complete data separation
- **Tenant Management:** Create/manage tenants from master
- **Per-Tenant Configuration:** Different settings per tenant
- **Tenant Analytics:** Compare across tenants
- **White-Label Per Tenant:** Different branding per tenant

### Advanced Learning Science
- **Full IRT Analysis:** 1PL, 2PL, 3PL item response models
- **Adaptive Testing:** CAT (Computerized Adaptive Testing)
- **Test Equating:** Compare scores across test versions
- **Standard Setting:** Angoff, modified Angoff methods
- **Reliability Reporting:** Full psychometric documentation

### Compliance Audit Reports
- **Automated Audit Trails:** Compliance-ready documentation
- **Access Reports:** Who accessed what student data
- **Change History Reports:** All modifications documented
- **Scheduled Compliance Checks:** Automated policy verification
- **Export for Auditors:** Formatted packages for external audits

### Enterprise SSO Enhancements
- **Multi-IdP Support:** Connect multiple identity providers
- **Just-In-Time Provisioning:** Create users on first login
- **Role Mapping:** Map IdP roles to WordPress/PPQ roles
- **Session Policies:** Enforce timeout, concurrent session limits
- **Federated Logout:** Proper session cleanup across systems

---

## Technical Requirements

### Dependencies
- Requires PressPrimer Quiz Free v2.0+
- Requires School Addon (which includes Educator)
- PHP 7.4+ (matches free plugin)
- WordPress 6.0+
- SSL required for proctoring features
- Adequate storage for proctoring media

### Database Extensions
```sql
-- Audit logging
ppq_audit_log (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    action VARCHAR(100),
    object_type VARCHAR(50),
    object_id BIGINT,
    old_value JSON NULL,
    new_value JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME,
    INDEX (user_id),
    INDEX (object_type, object_id),
    INDEX (created_at),
    INDEX (action)
)

-- Proctoring data
ppq_proctoring_sessions (
    id BIGINT PRIMARY KEY,
    attempt_id BIGINT,
    started_at DATETIME,
    ended_at DATETIME NULL,
    violations JSON,  -- Array of violation events
    flags JSON,  -- Summary flags
    reviewed_by BIGINT NULL,
    review_status ENUM('pending', 'approved', 'flagged', 'failed'),
    review_notes TEXT NULL,
    INDEX (attempt_id),
    INDEX (review_status)
)

ppq_proctoring_captures (
    id BIGINT PRIMARY KEY,
    session_id BIGINT,
    capture_type ENUM('photo', 'screenshot', 'video_segment'),
    file_path VARCHAR(255),
    captured_at DATETIME,
    ai_analysis JSON NULL,  -- AI detection results
    INDEX (session_id),
    INDEX (captured_at)
)

ppq_proctoring_events (
    id BIGINT PRIMARY KEY,
    session_id BIGINT,
    event_type VARCHAR(50),  -- tab_switch, copy_attempt, fullscreen_exit, etc.
    event_data JSON,
    created_at DATETIME,
    INDEX (session_id),
    INDEX (event_type)
)

-- API management
ppq_api_keys (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    key_hash VARCHAR(64),  -- Hashed API key
    key_prefix VARCHAR(8),  -- First 8 chars for identification
    name VARCHAR(100),
    permissions JSON,  -- Allowed endpoints/actions
    rate_limit INT DEFAULT 1000,  -- Requests per hour
    last_used DATETIME NULL,
    expires_at DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME,
    INDEX (key_hash),
    INDEX (user_id)
)

ppq_api_requests (
    id BIGINT PRIMARY KEY,
    api_key_id BIGINT,
    endpoint VARCHAR(255),
    method VARCHAR(10),
    response_code INT,
    response_time_ms INT,
    ip_address VARCHAR(45),
    created_at DATETIME,
    INDEX (api_key_id, created_at)
)

-- Webhooks
ppq_webhooks (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100),
    url VARCHAR(500),
    events JSON,  -- Which events trigger this webhook
    secret_key VARCHAR(64),  -- For signature verification
    is_active BOOLEAN DEFAULT TRUE,
    last_triggered DATETIME NULL,
    failure_count INT DEFAULT 0,
    created_by BIGINT,
    created_at DATETIME
)

ppq_webhook_deliveries (
    id BIGINT PRIMARY KEY,
    webhook_id BIGINT,
    event_type VARCHAR(50),
    payload JSON,
    response_code INT NULL,
    response_body TEXT NULL,
    delivered_at DATETIME NULL,
    status ENUM('pending', 'success', 'failed'),
    attempts INT DEFAULT 0,
    created_at DATETIME,
    INDEX (webhook_id, status)
)

-- White-label configuration
ppq_whitelabel (
    id BIGINT PRIMARY KEY,
    site_id BIGINT DEFAULT 1,  -- For multisite
    plugin_name VARCHAR(100),
    company_name VARCHAR(100),
    logo_url VARCHAR(500),
    primary_color VARCHAR(7),
    secondary_color VARCHAR(7),
    custom_css TEXT NULL,
    email_from_name VARCHAR(100),
    email_from_address VARCHAR(255),
    support_url VARCHAR(500),
    documentation_url VARCHAR(500),
    created_at DATETIME,
    updated_at DATETIME
)

-- Kirkpatrick tracking
ppq_kirkpatrick_programs (
    id BIGINT PRIMARY KEY,
    title VARCHAR(255),
    description TEXT,
    level_1_quiz_id BIGINT NULL,  -- Reaction survey
    level_2_quiz_ids JSON,  -- Learning assessments
    level_3_quiz_id BIGINT NULL,  -- Behavior followup
    level_4_metrics JSON,  -- Business outcome definitions
    created_by BIGINT,
    created_at DATETIME
)

ppq_kirkpatrick_results (
    id BIGINT PRIMARY KEY,
    program_id BIGINT,
    user_id BIGINT,
    level INT,  -- 1, 2, 3, or 4
    score DECIMAL(5,2),
    data JSON,
    recorded_at DATETIME,
    INDEX (program_id, user_id)
)
```

### Hooks Provided
```php
// Proctoring hooks
apply_filters('ppq_enterprise_proctoring_rules', $rules, $quiz_id);
apply_filters('ppq_enterprise_violation_severity', $severity, $event_type);
do_action('ppq_enterprise_proctoring_started', $session_id, $attempt_id);
do_action('ppq_enterprise_proctoring_violation', $session_id, $event);
do_action('ppq_enterprise_proctoring_ended', $session_id, $summary);
do_action('ppq_enterprise_capture_taken', $capture_id, $type);

// Audit hooks
apply_filters('ppq_enterprise_audit_actions', $actions_to_log);
apply_filters('ppq_enterprise_audit_retention_days', $days);
do_action('ppq_enterprise_audit_logged', $log_id, $action, $context);

// White-label hooks
apply_filters('ppq_enterprise_whitelabel_config', $config);
apply_filters('ppq_enterprise_email_template', $template, $email_type);
apply_filters('ppq_enterprise_admin_colors', $colors);

// API hooks
apply_filters('ppq_enterprise_api_endpoints', $endpoints);
apply_filters('ppq_enterprise_api_rate_limit', $limit, $key_id);
do_action('ppq_enterprise_api_request', $key_id, $endpoint, $method);
do_action('ppq_enterprise_api_error', $key_id, $error);

// Webhook hooks
apply_filters('ppq_enterprise_webhook_events', $events);
apply_filters('ppq_enterprise_webhook_payload', $payload, $event_type, $context);
do_action('ppq_enterprise_webhook_delivered', $delivery_id, $response);
do_action('ppq_enterprise_webhook_failed', $delivery_id, $error);

// Kirkpatrick hooks
apply_filters('ppq_enterprise_kirkpatrick_levels', $level_definitions);
do_action('ppq_enterprise_kirkpatrick_recorded', $program_id, $user_id, $level, $score);
apply_filters('ppq_enterprise_roi_calculation', $roi, $program_id, $metrics);

// Compliance hooks
apply_filters('ppq_enterprise_gdpr_fields', $fields_to_export, $user_id);
do_action('ppq_enterprise_data_exported', $user_id, $export_id);
do_action('ppq_enterprise_data_deleted', $user_id, $deletion_log);
apply_filters('ppq_enterprise_retention_policy', $policy, $data_type);
```

---

## UI Components

### Admin Additions
- **Enterprise Dashboard:** Organization-wide metrics and alerts
- **Proctoring Center:** Monitor active sessions, review flagged attempts
- **API Management:** Key generation, usage monitoring
- **Webhook Configuration:** Setup and test webhooks
- **White-Label Settings:** Branding configuration interface
- **Audit Log Viewer:** Search and filter audit events
- **Compliance Center:** GDPR requests, data retention, exports

### Proctoring Views
- **Live Monitor:** Real-time view of active proctored sessions
- **Session Review:** Photo/video review interface
- **Violation Report:** Flagged events with context
- **Proctoring Dashboard:** Aggregate statistics and trends

### Report Builder
- **Visual Designer:** Drag-drop report composition
- **Preview Mode:** See report before saving
- **Field Browser:** Available data fields with descriptions
- **Formula Editor:** Create calculated fields

### Kirkpatrick Interface
- **Program Builder:** Link quizzes to evaluation levels
- **Progress Tracker:** Student journey through levels
- **ROI Calculator:** Business impact analysis
- **Kirkpatrick Dashboard:** Summary across all programs

---

## Licensing & Activation

### License Validation
- EDD Software Licensing integration
- Up to 5 site activations
- Sites can be reassigned (deactivate/reactivate)
- Annual renewal required
- Grace period: 30 days (extended for enterprise)

### Feature Degradation
When license expires:
- All existing data preserved
- Proctoring disabled (existing sessions viewable)
- API keys deactivated
- Webhooks paused
- Audit logging continues (compliance requirement)
- White-label reverts to PressPrimer branding
- Report builder read-only

---

## Support Package

### Enterprise Support Includes
- **Priority Response:** 12-hour response time
- **Dedicated Contact:** Named support representative
- **Onboarding Call:** Initial setup assistance (1 hour)
- **Quarterly Check-ins:** Proactive support calls
- **Feature Requests:** Priority consideration
- **Emergency Support:** Critical issue escalation path

---

## Security Requirements

### Proctoring Security
- All captures encrypted at rest
- Captures auto-deleted after configurable period
- Student consent required before proctoring
- HTTPS required for all proctoring features
- No captures sent to external services by default

### API Security
- API keys hashed (not stored in plain text)
- Request signing for sensitive operations
- IP allowlisting option
- Automatic key rotation reminders

### Audit Log Security
- Logs append-only (no modification)
- Separate storage recommended
- Encrypted backup support
- Chain of custody maintained

---

## Success Metrics

- Conversion rate from School tier
- Proctoring adoption rate
- API integration count per customer
- White-label activation rate
- Audit log query frequency
- Renewal rate (target: 80%+)
- Support satisfaction score

---

## Development Priority

### v1.0 Priority Order
1. Proctoring (basic) - key enterprise differentiator
2. Audit logging - compliance requirement
3. White-label - branding control
4. API access - integration capability
5. Multi-site management - practical need

### v2.0 Priority Order
1. Advanced proctoring - competitive feature
2. Kirkpatrick tools - training market
3. Compliance features - GDPR/FERPA
4. Custom report builder - flexibility
5. Advanced integrations - ecosystem

### Security Priorities
- All proctoring code security audited
- API implementation follows OWASP guidelines
- Audit logging tamper-proof
- White-label doesn't create vulnerabilities
- Regular penetration testing recommended

### Integration Points
- Extends School addon (requires School)
- Proctoring uses separate media storage
- API runs through WordPress REST API
- Webhooks use Action Scheduler for reliability
- Audit logs can use separate database table/connection
