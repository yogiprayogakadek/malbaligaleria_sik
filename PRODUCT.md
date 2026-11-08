# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Unauthenticated applicants are tenants, contractors, vendors, event organizers, or their responsible contacts. They submit permit forms without an applicant account, keep a permit number or private status link, respond to revision requests, and retrieve an approved letter when available.
- Admin staff oversee the permit operation. Their routes cover permit search and reporting, accounts and validators, delegations, operating hours, service limits, document templates, communications, scanner operations, security settings, audit records, health checks, and recovery actions.
- Tenant Relationship (TR) validators review loading and unloading permits. TR also reviews work-permit categories routed to that division.
- Engineering, represented by the MEP validator role in routes, reviews technical work permits. Engineering decides whether a security deposit is required, makes the final permit decision, records completion, and manages deposit return steps.
- Event Promotion (EP) validators review event permits and work-permit categories routed to EP.
- Finance staff verify uploaded security-deposit payment proof against received funds. They can confirm receipt or ask the applicant to upload proof again.
- Secretary staff have a cross-workflow records view for loading, work, and event permits. The current Secretary routes expose review and communication history, not permit-decision actions.
- Security staff monitor permits valid today and recent gate activity. Scanner officers use a separate shift session with an officer ID and six-digit PIN to verify permit QR codes and record gate events.

## Product Purpose

SIK is Mal Bali Galeria's permit operations system for loading and unloading activity, contractor work, and events. It gives applicants a public submission and status path, routes each request to the responsible operational division, records human decisions, issues verifiable permit letters, and supports gate verification after approval.

Success means that each permit has an identifiable applicant, a visible current state, the right reviewer, a traceable decision path, and a permit document that Security can verify at the point of access.

## Positioning

The product connects public permit intake, division-specific review, security-deposit handling, official permit documents, and gate scanning in one MBG operating workflow. The route structure is organized around real staff responsibilities rather than a generic approval queue.

## Operating Context

### Loading and unloading permits

1. An applicant submits tenant and contact details, movement direction, schedule, goods and vehicle information, and required supporting documents. The form supports draft persistence, but file attachments must be selected again after a draft is restored.
2. TR reviews applicant data, identity and instruction documents, and the movement schedule.
3. TR can approve, reject with a reason, or request corrections to selected fields.
4. The applicant tracks the request by permit number, follows revision links when requested, and can view the official letter after approval.
5. Security verifies the permit QR code and can record check-in, check-out, and loading or unloading evidence at the gate.

### Work permits

1. An applicant submits contractor, tenant, responsible-contact, worker, location, schedule, category, work-detail, and supporting-document information. The selected category determines the reviewing division.
2. TR and EP handle work categories assigned to their divisions. They can approve, reject, or request a revision.
3. Engineering handles MEP-assigned work. It first decides whether a security deposit is required and records the amount and notes when applicable.
4. When a deposit is required, the applicant uploads payment proof through the private status page. Finance confirms that funds were received or asks for new proof. Engineering then makes the final permit decision.
5. After approval, staff can mark work complete. A deposit-bearing permit can move through refund processing, including the returned amount and refund proof.
6. Approved permit documents can be revoked. The staff view states that revocation disables the QR code and document download while retaining earlier process history.

### Event permits

1. An event organizer or tenant submits the event type, responsible contact, location, category, work details, dates, operational requirements, and notes.
2. The applicant may request a related loading and unloading permit letter as part of the event application.
3. EP reviews the application and can approve, reject, or request revisions.
4. The applicant follows a private status link and can view or download the official event letter after approval. The approved document includes a unique identifier and QR verification.

## Capabilities and Constraints

- Public submission is available only while the website-operating middleware allows it. Admin routes manage regular operating hours and exceptions.
- Public status, submission, revision, document, scanner, and health routes apply explicit throttles where abuse or repeated access is material.
- Applicant status access differs by workflow. Loading can be tracked by permit number; work and event status pages use high-entropy private tokens.
- Loading and work forms support saved drafts. Event submission does not expose the same draft routes.
- Permit review remains a human decision. The application routes work to a division and records decisions, but the views do not present automatic approval.
- The interface and operational copy are in Indonesian. Staff timestamps are presented in WITA in the reviewed views.
- Uploaded documents and generated permit letters are part of the workflow. Access to sensitive staff documents commonly uses signed URLs with short-lived links in the views.
- The scanner is a browser-based camera experience with a web-app manifest. It can require location access, and the view states that an officer shift session lasts no more than 12 hours.
- Data-heavy staff views use tables on wider screens and task-focused cards on mobile. Core actions must remain available in both layouts.

## Notifications

- Applicant-facing views state that email updates and private status links are scheduled after submission. Loading applicants are told to check the inbox or spam folder for updates.
- Admin, TR, Engineering, EP, Finance, Secretary, and Security routes expose an in-app notification feed with read and clear actions.
- Those staff areas also expose web-push subscription and removal routes.
- Admin routes provide email and WhatsApp settings, message templates, recipient management, communication logs, retry actions, and email fallback for failed WhatsApp delivery.
- Event operations have a dedicated WhatsApp-group configuration with group synchronization, membership and routing rules, rate limits, delivery history, and retry actions.
- Notification delivery is operationally observable through logs and retry controls. The product does not present delivery as guaranteed.

## Scanner and Gate Operations

- A scanner officer starts a shift session with an officer ID and six-digit PIN before camera scanning is enabled.
- The scanner verifies permit QR tokens, can enforce an admin-configured location boundary, and records gate events through throttled routes.
- For loading and unloading activity, the scanner can capture photo evidence of goods or a vehicle.
- Security sees permits valid today plus recent check-in and check-out activity. TR and Admin have loading-evidence views, while Admin also has scanner logs, evidence access, block removal, and security reconciliation.
- An expired, rejected, or revoked permit must not be represented as valid. Revocation is designed to disable the permit's QR and official document access.

## Security Posture

- Staff workspaces require authentication and role-specific middleware for Admin, TR, MEP, EP, Finance, Secretary, and Security.
- Authentication routes include password reset, required initial-password change, two-factor setup and challenge, recovery-code regeneration, profile management, and session revocation.
- Sensitive Admin areas add a separate confirmation middleware, and sensitive mutations apply request throttles.
- Public success pages, sensitive documents, payment or refund proof, and several revision or preview links use signed URLs or high-entropy tokens. Document identifiers and route patterns are constrained where the routes define them.
- Forms in the reviewed views include Laravel CSRF tokens. Public submission routes also use submission throttles and monitoring middleware.
- Official permit views expose document identity and SHA-256 information to authorized staff, and public approval verification is rate-limited.
- Scanner access combines an officer session, PIN entry, throttling, optional location restriction, evidence logging, and administrative block controls.
- These are observed application controls. They are not evidence of a named compliance certification, external audit, or absolute security guarantee.

## Evidence on Hand

- Product truth is visible in `routes/*.php` and `resources/views/**/*.blade.php`.
- Public forms, status pages, staff queues, decision panels, notification controls, scanner screens, and Admin settings provide direct evidence for the workflows documented here.
- The repository contains operational security and deployment documentation, but this product record does not convert those files into compliance claims.
- No verified customer metrics, testimonials, service-level guarantees, or certification claims are present in the reviewed routes and views. Future product copy must not fabricate them.

## Product Principles

- Keep the current permit state, responsible division, next action, and recorded history visible.
- Make public submission possible without weakening staff authorization or sensitive-document access.
- Keep approval, rejection, deposit confirmation, completion, refund, revocation, and gate verification as explicit human actions.
- Treat notifications as a delivery system with observable failures and recovery controls, not as proof that a person received a message.
- Preserve the connection between an approved document and its gate-verification record.

## Accessibility & Inclusion

- Public forms include explicit labels, validation messages, progress states, and mobile step navigation.
- Staff data views provide mobile card alternatives to desktop tables.
- Interactive controls must remain keyboard reachable, visibly focused, and at least 44px in touch area. Status must never rely on color alone.

## Explicit Non-Goals

- SIK is not presented as an applicant account portal. The current applicant journey is permit-number or private-link based.
- SIK is not a payment gateway or accounting ledger. Applicants upload payment proof, and Finance records whether funds were received.
- SIK does not make permit decisions autonomously. Staff validators remain responsible for review and decision actions.
- The scanner is not a general employee attendance system. Its visible purpose is permit verification and permit-related gate events.
- SIK is not a general-purpose document repository. Document access is tied to permit operations and role or token checks.
- SIK does not claim a security certification, guaranteed notification delivery, or a risk-free operating environment.
