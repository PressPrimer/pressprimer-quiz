# Enterprise Addon - Overview

## Product Identity

**Name:** PressPrimer Quiz - Enterprise  
**Price:** $499/year (5 sites)  
**Launched At:** Version 2.0 (aligned with Free v2.0)  
**Requires:** School addon (includes all Educator + School features)

## Target Users

Large organizations and institutions who need:
- High-stakes assessment integrity
- Complete customization and white-labeling
- Compliance and audit requirements
- Complex assessment logic

## Release Strategy

Each release focuses on 1-2 major features. Enterprise tier includes all Educator and School features.

---

## Version 2.0 - Launch Release

**Theme:** White-Label & Audit  
**Target:** Simultaneous with Educator/School v2.0

### Major Features

#### 1. White-Label
- Remove all PressPrimer branding from admin
- Custom plugin name in admin menu
- Custom plugin icon
- Custom email templates with organization branding
- Custom results page branding
- Custom "Powered by" text (or remove entirely)
- CSS customization panel for admin styling
- Logo upload for quiz interfaces

#### 2. Audit Logging
- Comprehensive event logging:
  - Quiz created/edited/deleted
  - Question created/edited/deleted
  - User permissions changed
  - Settings modified
  - Attempt started/submitted
  - Grade changes/curves applied
- Log viewer with filtering
- Log retention settings (30/90/365 days/forever)
- Log export (CSV, JSON)
- Immutable log storage (append-only)
- User session tracking

### Additional Features
- Enterprise settings panel
- Priority support badge in admin
- Custom capability configuration

---

## Version 2.1

**Theme:** Advanced Assessment  
**Target:** 6 weeks after v2.0

### Major Feature

#### Branching & Adaptive Logic
- Conditional question flow based on answers
- Skip logic (if answer X, skip to question Y)
- Section branching (if score < 50% on section 1, show remedial section)
- Adaptive difficulty (adjust difficulty based on performance)
- Question weights that change based on path
- Path visualization in quiz builder
- Analytics per branch path
- Minimum/maximum questions per attempt

### Supporting Features
- Question pools with weighted random selection
- Per-question time limits

---

## Version 2.2

**Theme:** Proctoring  
**Target:** 6 weeks after v2.1

### Major Feature

#### Proctoring Suite
- Lockdown browser detection (recommend/require)
- Tab/window focus monitoring
- Full-screen mode enforcement
- Copy/paste prevention
- Right-click disable
- Screenshot detection (JavaScript-based)
- Webcam snapshot capture (optional, consent-based)
- Proctor review dashboard
- Flag suspicious behavior
- Incident reports per attempt
- Browser fingerprinting
- IP consistency checking

### Supporting Features
- Proctor role with limited permissions
- Bulk review interface
- Automated suspicious pattern detection

---

## Version 2.3

**Theme:** Compliance & Reporting  
**Target:** 6 weeks after v2.2

### Major Feature

#### Compliance Reporting
- FERPA compliance documentation
- GDPR data export per user
- Right to deletion workflow
- Data retention policies with auto-purge
- Consent management
- Privacy policy integration
- Compliance audit reports
- Data processing agreements template

### Supporting Features
- Scheduled compliance reports
- Data residency documentation
- Security posture dashboard

---

## Version 2.4+

**Future Considerations:**
- API access for external integrations
- Custom webhook configurations
- SSO/SAML integration
- Multi-tenant architecture
- Custom question type SDK
- AI proctoring (behavioral analysis)
- Biometric verification

---

## Technical Architecture

### Plugin Structure
```
pressprimer-quiz-enterprise/
├── pressprimer-quiz-enterprise.php
├── includes/
│   ├── class-ppq-enterprise.php
│   ├── admin/
│   │   ├── class-ppq-white-label.php
│   │   ├── class-ppq-audit-log.php
│   │   ├── class-ppq-branching-builder.php
│   │   └── class-ppq-proctoring-admin.php
│   ├── services/
│   │   ├── class-ppq-audit-service.php
│   │   ├── class-ppq-branching-engine.php
│   │   ├── class-ppq-proctor-service.php
│   │   └── class-ppq-compliance-service.php
│   ├── frontend/
│   │   └── class-ppq-lockdown.php
│   └── models/
│       ├── class-ppq-audit-entry.php
│       ├── class-ppq-branch-rule.php
│       └── class-ppq-proctor-incident.php
├── assets/
│   ├── css/
│   ├── js/
│   │   └── lockdown.js
│   └── images/
└── languages/
```

### Database Tables
- `wp_ppq_audit_log` - Comprehensive event log
- `wp_ppq_white_label` - Branding configuration
- `wp_ppq_branch_rules` - Quiz branching logic
- `wp_ppq_proctor_incidents` - Flagged behaviors
- `wp_ppq_proctor_snapshots` - Webcam captures (if enabled)
- `wp_ppq_compliance_consents` - User consent records

### Hooks Added
```php
// Actions
do_action('ppq_enterprise_audit_log', $event_type, $event_data);
do_action('ppq_enterprise_proctor_incident', $attempt_id, $incident_type);
do_action('ppq_enterprise_branch_taken', $attempt_id, $from_question, $to_question);

// Filters
apply_filters('ppq_enterprise_plugin_name', $name);
apply_filters('ppq_enterprise_plugin_icon', $icon_url);
apply_filters('ppq_enterprise_audit_events', $event_types);
apply_filters('ppq_enterprise_lockdown_settings', $settings);
```

### Audit Log Schema
```sql
CREATE TABLE wp_ppq_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    object_type VARCHAR(50),
    object_id BIGINT UNSIGNED,
    user_id BIGINT UNSIGNED,
    user_ip VARCHAR(45),
    user_agent TEXT,
    event_data JSON,
    created_at DATETIME NOT NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_object (object_type, object_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;
```

### Branching Logic
```php
class PPQ_Branching_Engine {
    
    public function get_next_question($attempt_id, $current_question_id, $answer) {
        $rules = PPQ_Branch_Rule::get_for_question($current_question_id);
        
        foreach ($rules as $rule) {
            if ($this->evaluate_condition($rule, $answer)) {
                return $rule->target_question_id;
            }
        }
        
        // Default: next question in sequence
        return $this->get_sequential_next($attempt_id, $current_question_id);
    }
    
    private function evaluate_condition($rule, $answer) {
        switch ($rule->condition_type) {
            case 'answer_equals':
                return $answer === $rule->condition_value;
            case 'answer_contains':
                return in_array($rule->condition_value, (array) $answer);
            case 'score_above':
                return $this->get_current_score() >= $rule->condition_value;
            case 'score_below':
                return $this->get_current_score() < $rule->condition_value;
            default:
                return false;
        }
    }
}
```

---

## Success Metrics

### v2.0 Launch (30 days)
- 20+ paid licenses
- <2% refund rate
- 100% using white-label
- 80% using audit logging

### Ongoing
- 10% upgrade rate from School tier
- 90%+ renewal rate
- Average contract value increase via custom work
- Zero security incidents
