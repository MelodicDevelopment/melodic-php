<?php
namespace Melodic\DependencyInjection\Traits
{
	use Melodic\DependencyInjection\InjectionEngine;

	trait InjectionTrait
	{
		public static function InstantiateByInjection($params = [])
		{
			return InjectionEngine::Instantiate(get_called_class(), $params);
		}
	}
}
?>