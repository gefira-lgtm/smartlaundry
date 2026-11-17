<?php
/**
 * Central definition of which roles are allowed to access each platform metric.
 * Structure: [metric_key => ['owner_platform', 'admin_owner']]
 */
return [
    // Platform‑wide financial / operational metrics
    'gmv_total'                => ['owner_platform', 'admin_owner'],
    'total_orders'             => ['owner_platform', 'admin_owner'],
    'verified_stores'          => ['owner_platform', 'admin_owner'],
    'pending_verification_stores' => ['owner_platform', 'admin_owner'],
    'all_stores'               => ['owner_platform', 'admin_owner'],
    'total_customers'          => ['owner_platform', 'admin_owner'],
    // Add more metrics here if needed
];

