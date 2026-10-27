<?php

declare(strict_types=1);

class Z2MAutomations extends IPSModuleStrict
{
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetStatus(104);
    }
}
