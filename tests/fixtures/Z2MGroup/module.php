<?php

declare(strict_types=1);

class SyntheticZ2MGroup extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyInteger('GroupId', 7);
        $this->RegisterPropertyString('MQTTBaseTopic', 'synthetic/z2m');
        $this->RegisterVariableBoolean('state', 'Synthetic group state', [], 0);
        $this->EnableAction('state');
    }
}
