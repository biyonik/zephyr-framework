<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsOne;

class HasOne extends HasMany implements ReturnsOne
{
    public function getResults(): ?Model
    {
        $results = parent::getResults();
        return $results->first() ?: null;
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key][0] ?? null);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }
}