<?php

/**
 * Service to handle unit convertions
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use QD\commerce\shipmondo\records\OrderInfo;

class Units extends Component
{
    /**
     * Function to convert weight to grams
     * Shipmondo only accepts grams as weight unit
     *
     * @param integer $value
     * @param string $unit
     *
     * @return void
     */
    public function convertToGram(int $value, string $unit)
    {
        // If unit is already in grams, return value
        if ($unit == 'g') {
            return $value;
        }

        // If unit is in kg, convert to grams
        if ($unit == 'kg') {
            return $value * 1000;
        }

        // If unit is in lb, convert to grams
        if ($unit == 'lb') {
            return $value * 453.592;
        }

        // Fallback in case unsupported unit is passed
        return $value;
    }
}
