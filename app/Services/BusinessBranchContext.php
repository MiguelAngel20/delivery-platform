<?php

namespace App\Services;

final readonly class BusinessBranchContext
{
    public function __construct(
        public int $branchId,
    ) {}
}
