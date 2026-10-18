# Premium PBR presentation release

The owner requested a cohesive premium redesign across the whole system. The
release applies one forest/ivory/gold design language to the public website,
shared login/registration, Student app launcher, AI chat, calculators, saved
results, existing Workspace/PartnerDynamics shell and original Filament Admin.

Local Inter variable and Padauk fonts provide an offline fallback. Existing
Noto Myanmar faces continue to apply where the public/legacy layouts load them.
Icons use the existing SVG system; there are no emoji substitutes or new raster
dependencies. Typography, focus states, 44px touch controls, mobile grids,
scrollable AI messages, composer sizing, password visibility, loading feedback
and reduced-motion support are shared. App labels remain visible without hover.
Native Filament navigation, publishing forms, dark mode and component behavior
remain; an additive head hook loads presentation assets.

The release changes no controllers, models, routes, schema, account rights,
passwords, AI engine, assessment scoring or publishing data. It does not run the
previous destructive test-data reset. Approved owner-private knowledge uploads
and revised invitation rights are separate functional work, recorded in the ADR;
this presentation release does not advertise them as available.

`scripts/pbr-design-update.sh REVIEWED_COMMIT [APP_ROOT]` accepts the installed
baseline 93da710fa086bf5ae9320250da0011683afd2520 or that exact target. It checks
the presentation-only source allowlist, cached/environment configuration match,
isolated full application tests, fresh verified database/application backup and
restore, real web-user Filament/publishing/runtime render, exact record dump
equality, and live public/authentication HTTPS probes. Failure during cutover
rolls back code/configuration without restoring an older database. No migration,
seeder, role reassignment, environment edit or destructive cleanup runs.

Automated verification uses DOM checks for password controls, loading recovery,
streamed AI safety/history and calculator inputs, plus PHP/Blade/frontend and
disposable MySQL deployment checks. Public HTTPS uses a synthetic front door in
CI. A managed browser/control-browser is unavailable here; visual desktop/mobile
and signed-in publishing verification remains a live human check after deployment.
