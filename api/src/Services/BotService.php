<?php

namespace BattleVue\Services;

use BattleVue\Repos\BotRepo;
use BattleVue\Validators\BlueprintValidator;
use BattleVue\Validators\RulesetValidator;
use RuntimeException;

class BotService
{
    public function __construct(
        private BotRepo $botRepo,
        private BlueprintValidator $blueprintValidator,
        private RulesetValidator $rulesetValidator
    ) {
    }

    public function inventory(int $userId): array
    {
        return $this->botRepo->inventory($userId);
    }

    public function validateBlueprint(array $payload): array
    {
        $errors = $this->blueprintValidator->validate($payload);
        return ['valid' => count($errors) === 0, 'errors' => $errors];
    }

    public function validateRuleset(array $payload): array
    {
        $errors = $this->rulesetValidator->validate($payload);
        return ['valid' => count($errors) === 0, 'errors' => $errors];
    }

    public function createBlueprint(int $userId, array $payload): int
    {
        $validation = $this->validateBlueprint($payload);
        if (!$validation['valid']) {
            throw new RuntimeException('Invalid blueprint: ' . implode(' ', $validation['errors']));
        }
        return $this->botRepo->createBlueprint($userId, $payload);
    }

    public function updateBlueprint(int $userId, int $blueprintId, array $payload): void
    {
        $validation = $this->validateBlueprint($payload);
        if (!$validation['valid']) {
            throw new RuntimeException('Invalid blueprint: ' . implode(' ', $validation['errors']));
        }

        if (!$this->botRepo->updateBlueprint($userId, $blueprintId, $payload)) {
            throw new RuntimeException('Blueprint not found.');
        }
    }

    public function listBlueprints(int $userId): array
    {
        return $this->botRepo->listBlueprints($userId);
    }

    public function createRuleset(int $userId, array $payload): int
    {
        $validation = $this->validateRuleset($payload);
        if (!$validation['valid']) {
            throw new RuntimeException('Invalid ruleset: ' . implode(' ', $validation['errors']));
        }

        return $this->botRepo->createRuleset($userId, $payload);
    }

    public function updateRuleset(int $userId, int $rulesetId, array $payload): void
    {
        $validation = $this->validateRuleset($payload);
        if (!$validation['valid']) {
            throw new RuntimeException('Invalid ruleset: ' . implode(' ', $validation['errors']));
        }

        if (!$this->botRepo->updateRuleset($userId, $rulesetId, $payload)) {
            throw new RuntimeException('Ruleset not found.');
        }
    }

    public function listRulesets(int $userId): array
    {
        return $this->botRepo->listRulesets($userId);
    }

    public function getBlueprintForUser(int $userId, int $blueprintId): array
    {
        $bp = $this->botRepo->getBlueprint($userId, $blueprintId);
        if (!$bp) {
            throw new RuntimeException('Blueprint not found.');
        }
        return $bp;
    }

    public function getRulesetForUser(int $userId, int $rulesetId): array
    {
        $rs = $this->botRepo->getRuleset($userId, $rulesetId);
        if (!$rs) {
            throw new RuntimeException('Ruleset not found.');
        }
        return $rs;
    }
}
