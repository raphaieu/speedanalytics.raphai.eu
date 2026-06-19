<?php

namespace App\Enums\Demo;

enum RuleCompliance: string
{
    case Compliant = 'compliant';
    case Violated = 'violated';
    case NotApplicable = 'not_applicable';
}
