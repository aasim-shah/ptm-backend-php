# Shared portal design refresh — 20 October 2026

Implemented and deployed to the IP review site.

- Shared `portal-ui.css` applies consistent control sizes, rounded corners, focus states, section headings, coloured icon surfaces, tables and button styles across the role workspaces, account security, invitation activation, notifications and retained legacy administration forms.
- Profile settings now use a compact identity banner, grouped first/last-name fields, timezone help, an expandable password section and a separate school-connections panel. School access and email remain server-controlled.
- People & access places account connection and invitations side by side on desktop, with memberships and guardian review beneath. Delegated staff permissions remain available in an expandable section.
- Announcements, events, student updates, class sections, messaging, scheduling and attachments share the same form system. Narrow side panels stack fields; phone layouts use one column and 16px control text.
- Cards use balanced outlines and soft fills, with no decorative one-sided borders. Role accents retain the client’s colourful direction.

Existing POST actions, CSRF fields, field names, access checks, validation and MFA skip behavior are preserved. No database migration or production record changes were required for this visual update.

Verification: 55 PHPUnit tests / 313 assertions passed; Blade compilation and whitespace checks passed. Browser checks covered desktop and phone profile layouts, people/access, class sections, scheduling, student updates and account security. The profile form saved successfully with unchanged local demo values. Live authenticated checks passed for 58 permitted pages across all five demo portal roles, stylesheet delivery, MFA skip and readiness. The live browser reported no console warnings/errors. Phone checks found no document overflow and balanced card outlines.

Design concept: https://superdesign.dev/teams/224d1904-f5ab-409d-a45e-7851f6ae20c0/projects/b686bf43-d4a5-4953-98c7-60ebaf9d27f4

The review VPS retains image `ptm-backend:before-portal-ui-refresh` for a visual rollback. The active source is `/root/ptm-candidate`; the root-only patch/build record is under `/root/ptm-deploy-backups/portal-ui-patch`. A scoped image layer contains only the updated views and CSS, with no environment or runtime data.
