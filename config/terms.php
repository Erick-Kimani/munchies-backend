<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Terms documents
    |--------------------------------------------------------------------------
    |
    | One entry per audience. The key is what the frontend sends as
    | `audience`, and the version is what it sends as
    | `accepted_terms_version`.
    |
    | MUST stay in step with src/data/terms.js on the frontend, where the
    | actual wording lives. The backend deliberately does not store the
    | prose — it stores *which version* a user accepted and when, which is
    | the part that has to be provable later. Keep the accepted wording of
    | each version in version control (and, for anything you might have to
    | produce in a dispute, a PDF in your own records).
    |
    | When you publish new wording:
    |   1. change the copy in src/data/terms.js
    |   2. bump TERMS_VERSION there and the matching version here
    |   3. ship both together
    |
    | Existing rows in terms_acceptances keep the old version string, so
    | you can always answer "what exactly did this user agree to, and when".
    |
    */

    'documents' => [

        // Everyone with an account: buyers, tenants, browsers.
        // Accepted once, at registration.
        'general' => [
            'version' => '2026-09-16',
            'label' => 'Buyer & Tenant Terms',
        ],

        // Anyone publishing a property. Accepted per listing, because the
        // declaration is about that specific property.
        'seller' => [
            'version' => '2026-09-16',
            'label' => 'Seller Terms',
        ],

    ],

];