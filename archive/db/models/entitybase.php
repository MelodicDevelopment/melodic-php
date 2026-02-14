<?php
/**
 * @name EntityBase
 * @description A base class for all entities
 * @package Melodic
 */

namespace Models
{
	use Contracts;

	abstract class EntityBase implements Contracts\iEntityBase
	{
		/** public properties */
		public $CreateDate = 0;
		public $LastModifiedDate = 0;
	}
}