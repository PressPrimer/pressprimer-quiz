=== PressPrimer Quiz ===
Contributors: pressprimer
Tags: quiz, learndash, assessment, lms, elearning
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enterprise-grade quiz builder plugin with AI question generation, LMS integration, and beautiful themes. Free forever.

== Description ==

**PressPrimer Quiz** is a professional assessment plugin that bridges the gap between basic quiz plugins and expensive enterprise platforms. Create beautiful, engaging quizzes with AI-powered question generation, deep LMS integration, and the reporting features serious educators need—all without monthly fees or per-student pricing.

**This isn't a crippled trial.** The free version is genuinely useful forever with unlimited quizzes, unlimited questions, AI generation, LMS integration, and three professional themes. We earn upgrades by delivering specialized features, not by hobbling what you get for free.

https://www.youtube.com/watch?v=YHyooYXKLo0

= Why Choose PressPrimer Quiz? =

Most WordPress quiz plugins were built for BuzzFeed-style trivia and basic scoring, not serious assessment. Meanwhile, built-in LMS quiz tools are afterthoughts with limited reporting, basic features, and dated interfaces. Enterprise LMS platforms cost $10,000-$100,000 per year with per-user fees.

PressPrimer Quiz delivers enterprise-grade assessment and includes all of the critical features you normally expect in premium plugins at no cost:

* **AI-Powered Question Generation** – Use your own OpenAI API key to generate questions from text, PDFs, or Word documents. No credits to buy, no middleware fees, no limits.
* **Deep LMS Integration** – Native integration with popular WordPress LMS plugins, including LearnDash, Tutor LMS, and LifterLMS. Quizzes appear in lessons or topics, trigger completions, and respect enrollment—automatically.
* **Modern, Beautiful Design** – Three professional themes that rival SaaS products. Your quizzes won't look like forms from 2005.
* **Real Reporting** – Score distribution, category breakdowns, time analytics, confidence reporting, and attempt history. Data captured now powers future psychometric features.
* **Built to Prevent Cheating** – Server-side answer validation means correct answers are never exposed in page source. You can also limit attempts and force delays between retries.
* **Server-Side Resume That Works Everywhere** – Every answer is instantly saved to the server. Students can pause on their phone and resume on their laptop without losing a single response.

= Free Features That Cost Money Elsewhere =

PressPrimer Quiz includes features in the free version that competitors charge for:

* **Unlimited quizzes and questions** – No artificial limits
* **AI question generation** – Bring your own OpenAI API key
* **LMS integrations** – LearnDash, Tutor LMS & LifterLMS, with more coming
* **Question banks** – Organize and reuse questions across quizzes
* **Dynamic quiz generation** – Pull random questions based on category, difficulty, and question bank
* **Server-side save and resume** – Students can pause and continue later from any device
* **Guest support** – Optional email capture for non-registered users
* **Score-banded feedback** – Different messages based on performance
* **Per-question and per-answer feedback** – Explain correct and incorrect answers
* **Confidence ratings** – Optional checkbox for students to indicate certainty
* **Three professional themes** – Default, Modern, and Minimal
* **Uncanny Automator integration** – Triggers for quiz completion, pass, and fail, with a comprehensive set of tokens for use in automations

= Perfect For =

* **Course creators** using LearnDash, TutorLMS, or LifterLMS who need better quizzes than built-in tools
* **Corporate trainers** running compliance assessments at scale
* **University departments** with thousands of students needing detailed analytics
* **Test prep programs** requiring support for question pools with thousands of items
* **Testing providers** who need enterprise reliability without enterprise pricing
* **Course entrepreneurs** selling premium educational content

= Focused on What Matters for Learning =

PressPrimer Quiz concentrates on multiple choice, multiple answer, and true/false question types to deliver the best possible experience for learning and performance assessment. By focusing on these core formats, we deliver enterprise-quality features—massive question banks, sophisticated scoring, detailed analytics, and bulletproof reliability—rather than spreading thin across dozens of mediocre options.

= Built-in Integrations =

PressPrimer Quiz automatically detects and integrates with popular WordPress LMS plugins:

**LearnDash:** Attach quizzes to lessons and topics via meta box. Passing a quiz can automatically mark the lesson or topic complete. 

**Tutor LMS:** Add quizzes directly in the course builder.

**LifterLMS:** Meta box on lessons with completion triggers. 

**Uncanny Automator:** Three triggers available: User completes a quiz, user passes a quiz, user fails a quiz. 

All integrations are bundled in the free version.

= Coming Soon =

We're actively developing new features for upcoming premium addons:

* **Quiz assignment system** – Assign specific quizzes to users and groups with due dates
* **Group management** – Create and manage student groups for class-based instruction (including full LearnDash group and Group Leader integration)
* **Front-end quiz creation** – Allow teachers *and students* to build quizzes without admin access
* **Advanced reports and charts** – From question analysis to pre and post-test comparisons to group trends over time, there will be a report for everything that educators need
* **Import/Export** – Get questions and reporting data into and out of PressPrimer Quiz
* **xAPI/LRS output** – Learning analytics for enterprise deployments
* **LaTeX math support** – Mathematical notation for STEM assessments
* **More AI integration** - Generate distractors and feedback for your own questions, proofread imported content, and more

= Scale-Ready Architecture =

Built for serious deployment:

* Custom database tables handle 10,000+ questions and 100,000+ attempts
* Object caching support for high-traffic sites
* WCAG 2.1 AA accessibility compliance
* Full internationalization with RTL support

= Documentation & Support =

* [Knowledge Base](https://pressprimer.com/knowledge-base/)

= Source Code & Development =

The full uncompressed source code for all JavaScript and CSS files is available in our public GitHub repository:

* [GitHub Repository](https://github.com/PressPrimer/pressprimer-quiz)

The `/src` directory contains all unminified source files. The plugin uses webpack for building production assets. To rebuild from source:

1. Clone the repository
2. Run `npm install` to install dependencies
3. Run `npm run build` to compile assets

== Installation ==

= Automatic Installation =

1. Go to **Plugins → Add New** in your WordPress admin
2. Search for "PressPrimer Quiz"
3. Click **Install Now** and then **Activate**
4. Navigate to **PressPrimer Quiz** in your admin menu to get started

= After Activation =

1. Go to **PressPrimer Quiz → Settings** to configure defaults
2. (Optional) Add your OpenAI API key for AI question generation
3. Create your first question bank under **Question Banks**
4. Build a quiz under **Quizzes → Add New**
5. Embed using Gutenberg blocks, shortcodes, or LMS integration

= LMS Integration =

If you have LearnDash, TutorLMS, or LifterLMS installed, integration features enable automatically. No configuration required—just edit a lesson or topic and you'll see the quiz attachment options.

== Frequently Asked Questions ==

= Is this really free forever, or is it a limited trial? =

It's really free forever and not locked down. PressPrimer Quiz includes unlimited quizzes, unlimited questions, AI generation (with your own API key), LMS integrations, confidence ratings, per-question feedback, and three professional themes in the free version. We believe in earning upgrades by offering genuinely valuable premium features, not by crippling the free experience.

= How does AI question generation work? =

You provide your own OpenAI API key in the plugin settings. When generating questions, the plugin calls OpenAI directly—no middleware, no credits to purchase, no per-question fees. You pay OpenAI directly at their standard API rates (typically pennies per quiz). Generate from pasted text or uploaded PDF/Word documents. Review and edit every question before adding items to question banks.

= How does the server-side resume feature work? =

Every time a student selects an answer, it's immediately saved to your WordPress database. If users close their browser, switch devices, or lose internet connection, their progress is preserved. When they return—even from a different device—they pick up exactly where they left off. This works for logged-in users automatically; guest users maintain progress via session tokens.

= Does it work without an LMS plugin? =

Absolutely. PressPrimer Quiz works as a standalone quiz plugin. Use Gutenberg blocks or shortcodes to embed quizzes in any page or post. The LMS integrations are a bonus that enable automatically when an LMS is detected—they don't restrict standalone use.

= Will this conflict with my LMS's built-in quizzes? =

No. PressPrimer Quiz operates independently from native LMS quiz features. You can use both simultaneously—our quizzes appear via meta boxes and don't modify or interfere with built-in quiz functionality.

== External Services ==

This plugin connects to external third-party services. Use of these services is optional and requires explicit user configuration.

= OpenAI API =

This plugin offers optional AI-powered question generation using the OpenAI API. This feature is **disabled by default** and only activates when an administrator or authorized user enters their own OpenAI API key in the plugin settings.

**What data is sent:**
* Text content pasted by the user for question generation
* Content extracted from PDF or Word documents uploaded by the user
* Configuration parameters (number of questions, difficulty level, question types)

**When data is sent:**
* Only when a user explicitly clicks "Generate Questions" in the AI generation interface
* Data is never sent automatically or in the background

**What data is NOT sent:**
* Student quiz answers or attempt data
* User personal information
* Site configuration or other plugin data

**Service provider:** OpenAI, L.L.C.
* [Terms of Use](https://openai.com/policies/terms-of-use/)
* [Privacy Policy](https://openai.com/policies/privacy-policy/)
* [API Data Usage Policy](https://openai.com/policies/api-data-usage-policy/)

**Note:** You are responsible for your own use of the OpenAI API and must agree to OpenAI's terms when obtaining an API key. API usage costs are billed directly by OpenAI to the API key holder.

== Screenshots ==

1. PressPrimer Dashboard with key stats and quick actions
2. Question editor with contextual help
3. AI question generation with document upload
4. Quiz editor with multiple quiz types
5. Admin reporting with analytics

== Changelog ==

= 1.0.0 =
* Initial release
* Three question types: Multiple Choice, Multiple Answer, True/False
* Unlimited questions and question banks
* Visual quiz builder with fixed and dynamic modes
* AI question generation via OpenAI API
* Three professional themes: Default, Modern, Minimal
* LearnDash integration with lesson/topic embedding
* TutorLMS integration with course builder support
* LifterLMS integration with completion triggers
* Uncanny Automator integration with three triggers
* Guest support with optional email capture
* Server-side save and resume functionality
* Score-banded feedback system
* Per-question and per-answer feedback
* Confidence ratings
* Admin reporting dashboard
* Gutenberg blocks and shortcodes
* WCAG 2.1 AA accessibility compliance
* Full internationalization support

== Upgrade Notice ==

= 1.0.0 =
Initial release of PressPrimer Quiz. Enterprise-grade quizzes with AI generation and LMS integration—free forever.