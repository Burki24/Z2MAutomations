<?php

declare(strict_types=1);

class SyntheticZ2MDevice extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString('IEEE', '');
        $this->RegisterPropertyString('MQTTBaseTopic', 'synthetic/z2m');
        foreach (['state', 'state_l1', 'state_l2', 'state_l3'] as $ident) {
            $this->RegisterVariableBoolean($ident, 'Synthetic ' . strtoupper($ident), [], 0);
            $this->EnableAction($ident);
        }
        $this->RegisterVariableString('cover_state', 'Synthetic cover state', [], 0);
        $this->EnableAction('cover_state');
        $this->RegisterVariableString('action', 'Synthetic button action', [], 0);
        $this->RegisterVariableInteger('level', 'Synthetic level', [], 0);
        $this->EnableAction('level');
        $this->RegisterVariableInteger('write_only', 'Synthetic write only', [], 0);
        $this->EnableAction('write_only');
        $this->RegisterVariableBoolean('display_flag', 'Synthetic display only', [], 0);
        $this->RegisterVariableFloat('temperature', 'Synthetic temperature', [], 0);
    }
}
