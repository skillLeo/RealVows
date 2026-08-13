# Hero backup

`.BEFORE` files are the state at commit 23306b8 — the flat-arcade hero, before
any of today's work. The plain files are the state at the time of the backup
(perspective doorway, before the two figures were added).

Restore the old hero:
    git checkout 23306b8 -- resources/css/app.css \
        resources/views/public/partials/the-meeting.blade.php
    npm run build

Restore this backup instead:
    cp _backup/<stamp>/app.css resources/css/app.css
    cp _backup/<stamp>/the-meeting.blade.php resources/views/public/partials/
    npm run build

NOTE: commit 27d1857 ("A trusted contact can take back a yes") committed the
guardian work AND the hero rewrite together. Reverting that commit would take
the guardian work with it — use the file-level checkout above instead.
