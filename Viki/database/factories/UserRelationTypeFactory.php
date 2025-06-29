<?php

use \nocoding\Evaluation\Models\UserRelationTypes;

$factory->define(UserRelationTypes::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->word,
    ];
});