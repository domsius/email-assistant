<?php

return [
    'plans' => [
        'free' => [
            'name' => 'Free',
            'description' => 'Perfect for getting started',
            'price' => 0,
            'email_limit' => 100,
            'features' => [
                '100 emails per month',
                '1 email account',
                'Basic AI responses',
                'Email sync every 30 minutes',
            ],
        ],
        'starter' => [
            'name' => 'Starter',
            'description' => 'Great for small teams',
            'price' => 29,
            'email_limit' => 1000,
            'features' => [
                '1,000 emails per month',
                '3 email accounts',
                'Advanced AI responses',
                'Email sync every 10 minutes',
                'Priority support',
            ],
        ],
        'professional' => [
            'name' => 'Professional',
            'description' => 'For growing businesses',
            'price' => 99,
            'email_limit' => 5000,
            'features' => [
                '5,000 emails per month',
                '10 email accounts',
                'Advanced AI with custom training',
                'Real-time email sync',
                'API access',
                'Priority support',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'description' => 'Unlimited power for large teams',
            'price' => 299,
            'email_limit' => 50000,
            'features' => [
                '50,000 emails per month',
                'Unlimited email accounts',
                'Custom AI models',
                'Real-time sync',
                'Dedicated support',
                'Custom integrations',
                'SLA guarantee',
            ],
        ],
    ],

    'default_plan' => 'free',
];
