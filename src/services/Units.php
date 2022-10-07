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
	public function convertToGram(int $value, string $unit)
	{
		if ($unit == 'g') {
			return $value;
		}

		if ($unit == 'kg') {
			return $value * 1000;
		}

		if ($unit == 'lb') {
			return $value * 453.592;
		}

		return $value;
	}
}
