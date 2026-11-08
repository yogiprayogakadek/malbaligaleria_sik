---
name: "SIK Mal Bali Galeria"
description: "A calm, firm, and trustworthy permit operations system for Mal Bali Galeria."
colors:
  primary: "#3b82f6"
  primary-active: "#2563eb"
  canvas: "#f1f5f9"
  surface: "#ffffff"
  text-primary: "#0f172a"
  text-secondary: "#475569"
  border: "#cbd5e1"
  border-subtle: "#e2e8f0"
  success: "#15803d"
  warning: "#b45309"
  danger: "#dc2626"
typography:
  display:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    fontSize: "clamp(1.375rem, 3vw, 1.875rem)"
    fontWeight: 800
    lineHeight: 1.2
    letterSpacing: "-0.03em"
  headline:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    fontSize: "1rem"
    fontWeight: 700
    lineHeight: 1.4
    letterSpacing: "0em"
  body:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.6
    letterSpacing: "0em"
  label:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "0em"
rounded:
  sm: "8px"
  md: "12px"
  lg: "16px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  2xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary-active}"
    textColor: "{colors.surface}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "0 20px"
    height: "44px"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "0 18px"
    height: "44px"
  input-field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "0 12px"
    height: "44px"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "24px"
---

# Design System: SIK Mal Bali Galeria

## Overview

**Creative North Star: "The Calm Operations Desk"**

SIK should feel like a well-run operations desk at Mal Bali Galeria: calm under pressure, firm about state and responsibility, and trustworthy when a permit changes hands. Blue marks action and active context; neutral canvas and white surfaces keep operational records legible.

Density comes from real permit work, not decoration. Queue, review, status, and scanner surfaces may vary in composition, but they share one hierarchy, one semantic state language, and one restrained interaction model. Every screen has one clear focal action.

Dial: ENERGY 1 / RHYTHM 2 / MOTION 1

**Key Characteristics:**

- Calm operational hierarchy with one focal action per screen.
- Firm semantic states that pair color with plain-language labels.
- Trustworthy records presented on flat, bordered surfaces.
- Responsive workflows that reflow from tables to task-focused cards.
- Motion limited to short interaction and state feedback.

## Colors

The palette uses one operational blue, cool neutral surfaces, dark slate text, and semantic colors reserved for real workflow states.

### Primary

- **Action Blue:** The primary token marks focus, selection, links, and non-text accents. The darker active token carries filled controls and active navigation.

### Neutral

- **Canvas:** The cool gray page ground separates the application shell from white work surfaces.
- **Surface:** White holds forms, queue regions, dialogs, menus, and records.
- **Primary Text:** Near-black slate carries headings, values, and decisive labels.
- **Secondary Text:** Muted slate carries supporting context without dropping below readable contrast.
- **Borders:** Medium and subtle slate borders define structure without making every surface look boxed in.

### Semantic

- **Success:** Use only for approved, completed, verified, or successfully saved states.
- **Warning:** Use for pending review, revision, expiring attention, or another state that requires care without indicating failure.
- **Danger:** Use for rejection, revocation, destructive actions, and blocking errors.

### Named Rules

**The Action Blue Rule.** Use primary blue for focus, selection, links, and active context. Use `#2563eb`, not `#3b82f6`, behind normal-size white text because the darker blue meets WCAG AA.

**The Semantic Color Rule.** Success, warning, and danger colors communicate real states and always appear with text.

## Typography

**Display Font:** Inter with Apple system, BlinkMacSystemFont, Segoe UI, and sans-serif fallbacks.

**Body Font:** Inter with the same system fallbacks.

**Character:** Inter supports repeated identifiers, dense tables, forms, and mixed desktop and mobile use without adding an editorial personality that competes with operational content.

### Hierarchy

- **Display:** Heavy, compact page titles use the display token. Each page has one title at this level.
- **Headline:** Section and panel headings use the headline token. They name the task or record group, not a marketing idea.
- **Body:** Forms, descriptions, tables, status explanations, and history entries use the body token.
- **Label:** Field labels, buttons, compact metadata, and table controls use the label token. Uppercase is limited to short table headers where scanning benefits.
- **Identifiers:** Permit numbers may use the platform monospace stack at body size. Monospace is functional here because it separates identifiers from prose.

### Named Rules

**The Operational Type Rule.** Inter is used for dense, repeated records because its open forms remain legible at dashboard sizes; system fallbacks preserve the same practical tone.

## Layout

Use the 4, 8, 12, 16, 24, and 32px spacing scale. Small gaps bind labels to controls or metadata to values. Larger gaps separate task regions, sections, and page-level decisions.

The stable shell and repeated control placement provide the calm base required by ENERGY 1. RHYTHM 2 allows purposeful changes between queues, permit details, public forms, and the scanner. Do not force each surface into the same card grid. Each screen keeps one focal action, while secondary actions remain nearby but visually quieter.

Desktop queue views may use tables when comparison across rows is the task. At narrower widths, reflow the same records into cards or another vertical structure with the deciding fields and actions intact. Forms collapse to one column before labels or controls become cramped. Navigation changes into a mobile pattern instead of compressing a desktop row. No viewport may introduce page-level horizontal scrolling.

Every interactive target has a minimum 44 by 44px hit area. Adjacent targets retain enough space to avoid accidental activation. Fixed navigation and scanner controls reserve their space so content remains reachable.

## Elevation & Depth

The system is flat by default. Canvas, border, spacing, and state color establish hierarchy. Resting cards, tables, fields, buttons, navigation, alerts, and status badges do not cast shadows.

Dropdown menus and dialogs may use the documented overlay shadow because they temporarily sit above the current task. Focus rings are interaction indicators, not elevation, and remain visible on every control.

### Shadow Vocabulary

- **Overlay:** `0 12px 28px rgba(15, 23, 42, 0.10)` for dropdown menus and dialogs only.

### Named Rules

**The Flat-by-Default Rule.** Cards and controls stay flat at rest; only dropdowns and dialogs cast a shadow because they sit above the current task.

## Shapes

The form language is gently squared and operational. Use 8px corners for fields, compact controls, alerts, and status containers; 12px for buttons, standard panels, and cards; 16px for large containers and dialogs.

Full pills are reserved for true status badges and compact tags whose content stays on one line. Inputs, buttons, cards, dialogs, and navigation containers must not become pills.

### Named Rules

**The Radius Hierarchy Rule.** Use 8px for controls, 12px for standard surfaces, and 16px for large containers and dialogs; reserve full pills for true statuses or compact tags.

## Components

Components should feel precise, stable, and ready for repeated operational use. Every control must expose default, hover, focus-visible, active, disabled, loading, error, and success states when those states can occur.

### Buttons

- **Primary:** Use the active blue background, white label, 12px radius, and a 44px minimum height. One primary action leads each decision area.
- **Secondary:** Use a white background, medium border, primary text, 12px radius, and a 44px minimum height.
- **Destructive:** Use danger text and border on white by default. A destructive action names the object and requires confirmation when the effect is difficult to reverse.
- **Copy:** Use the operation itself, such as `Setujui permohonan`, `Verifikasi dana`, or `Unduh surat`. Do not use generic action labels.
- **State:** Hover and active feedback may shift position or border subtly over 150 to 200ms. Loading keeps the button width stable and prevents duplicate submission.

### Inputs and Fields

- **Structure:** Put a persistent label above each control. Use placeholders only as examples, never as the label.
- **Shape:** Use an 8px radius, white background, visible medium border, and a 44px minimum height.
- **Focus:** Use primary blue on the border or a clearly visible focus outline. Do not remove the browser outline without an equivalent replacement.
- **Validation:** Place specific error text next to the field and connect it programmatically. Do not communicate validity by border color alone.
- **Uploads:** State accepted formats and size limits before submission. Preserve the filename or chosen-file state after selection.

### Cards and Containers

- **Standard surface:** Use white, a subtle border, a 12px radius, and spacing from the approved scale. Keep it flat.
- **Large container:** Use a 16px radius only when the container groups a complete queue, form, or dialog-sized task.
- **Hierarchy:** Use cards when content needs a bounded unit. Prefer rows, definition lists, or sections when a card would add no structure.

### Data Tables and Queue Records

- **Desktop:** Columns follow the decision staff must make. Put the deciding fields early and keep the row action explicit.
- **Mobile:** Recompose each record as a vertical card with the same status, key fields, and action. Do not hide a required action in overflow.
- **States:** Provide empty, loading, error, filtered-empty, and permission-limited treatments. Empty states explain why the list is empty and name the next available action.
- **Density:** Compact density may reduce row padding, but it must not shrink interactive targets below 44px.

### Status Badges and Alerts

- **Badges:** Use semantic color, a plain-language label, and an optional relevant icon or dot. A dot never stands alone.
- **Alerts:** Use a semantic border or icon plus a concise explanation and next action. Alerts stay inline with the affected task.
- **Meaning:** Pending, revision required, approved, rejected, revoked, completed, payment review, and refund states keep stable wording across queues and details.

### Navigation

- **Active state:** Combine active blue with a calm neutral background and a visible label. The active location must remain clear without relying on the icon alone.
- **Destinations:** Every item points to an existing route or action. Do not add speculative destinations.
- **Responsive:** Desktop side navigation may collapse, while mobile navigation uses a labeled menu or a short set of primary destinations. Fixed navigation never covers content.

### Notifications, Dropdowns, and Dialogs

- **Notification center:** Distinguish unread items with label, weight, and state, not color alone. Show the event, record reference, and time when available.
- **Dropdown:** Use a white surface, 8px radius, the overlay shadow, complete keyboard navigation, and click or tap behavior equivalent to hover.
- **Dialog:** Use a 16px radius and the overlay shadow. Trap focus, label the dialog, close on Escape, restore focus to the trigger, and keep the final action explicit.

### Scanner

- **Camera task:** Keep the scan target, current instruction, officer state, and verification result visually separate.
- **Feedback:** Show clear scanning, success, invalid, expired, revoked, location-required, and network-error states.
- **Controls:** Use 44px or larger touch targets and do not rely on hover. Check-in, check-out, evidence capture, and session-ending actions must be plainly labeled.

### Named Rules

**The Motion Restraint Rule.** MOTION 1 permits short hover, focus, disclosure, and state transitions only; it does not permit scroll reveals, parallax, bouncing, pulsing, or decorative loops.

## Do's and Don'ts

### Do:

- Do use `#2563eb` for filled actions with normal-size white text; reserve `#3b82f6` for focus, selection, links, and non-text accents.
- Do keep one primary action visible in each decision area and label it with the actual operation.
- Do pair every status color with a plain-language label and, where helpful, a relevant icon.
- Do provide empty, loading, error, success, disabled, and permission-limited states where the workflow can reach them.
- Do reflow desktop tables into mobile cards or another task-focused layout without losing fields or actions.
- Do preserve 44px minimum touch targets, visible focus, logical keyboard order, and Escape-close behavior for dialogs.

### Don't:

- Don't put normal-size white text on `#3b82f6`; the verified ratio is 3.68:1.
- Don't use shadows on cards, tables, fields, status badges, or resting buttons.
- Don't use gradients, glow, glass, decorative status dots, or motion loops.
- Don't turn every element into a pill or every record into an identical floating card.
- Don't communicate approval, rejection, warning, or access state by color alone.
- Don't invent counts, claims, identities, or activity to make an empty operational screen look busy.
- Don't ship dead controls, unreachable navigation, or desktop layouts squeezed onto mobile.
