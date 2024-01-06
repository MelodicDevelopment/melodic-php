<?php
namespace Melodic\DependencyInjection
{
	use Melodic\DependencyInjection\Module;
	use Melodic\Utility\LibraryHelper;

	class InjectionEngine
	{
		private array $_map = array();

		public function Map(Module $module)
		{
			$this->_map[$module->token] = $module;
		}

		public function Create($className, $params = [])
		{
			$class = new \ReflectionClass($className);
			$constructor = $class->getConstructor();

			if ($constructor != null){
				$injectableParams = $this->GetInjectableParams($constructor->getParameters());
				
				foreach ($injectableParams as $param){
					$injectable = array_filter($param->getAttributes(), function($attr){
						return $attr->getName() == "Melodic\DependencyInjection\Attributes\Inject";
					})[0]->newInstance();

					if (!array_key_exists($injectable->token, $this->_map)) {
						throw new \Exception("Missing injectable dependency: ".$injectable->token);
					}

					$dependency = $this->_map[$injectable->token];
					
					if ($dependency->singleton){
						if ($dependency->instance == null){
							$dependency->instance = $this->Create($dependency->implementation);
						}
						$params[] = $dependency->instance;
					} else {
						$params[] = $this->Create($dependency->implementation);
					}
				}
			}

			$instance = null;
			if ($constructor != null && count($params) > 0) {
				$instance = $class->newInstanceArgs($params);
			} else {
				$instance = $class->newInstance();
			}

			return $instance;
		}

		#region private methods

		private function GetInjectableParams(array $parameters): array
		{
			return array_filter($parameters, function($param){
				$attrs = $param->getAttributes();
				$attrNames = array_map(function($attr){
					return $attr->getName();
				}, $attrs);

				if (count($attrNames) > 0 && in_array("Melodic\DependencyInjection\Attributes\Inject", $attrNames)){
					return true;
				}

				return false;
			});
		}

		#endregion

		#region static functions

		public static function GetGlobalInjectionEngine(): InjectionEngine
		{
			$key = "INJECTION_ENGINE";
			
			if (!isset($GLOBALS[$key])) {
				$GLOBALS[$key] = new InjectionEngine();
			}

			return $GLOBALS[$key];
		}

		public static function Instantiate($className, $params = [])
		{
			$injectionEngine = InjectionEngine::GetGlobalInjectionEngine();
			return $injectionEngine->Create($className, $params);
		}

		public static function Register(Module $module): void
		{
			$injectionEngine = InjectionEngine::GetGlobalInjectionEngine();
			$injectionEngine->Map($module);
		}

		public static function RegisterFromLibrary(string $namespace, string $namespaceDirectory, array $ignored = [".", ".."])
		{
			$injectableAttributeName = "Melodic\\DependencyInjection\\Attributes\\Injectable";
			$classes = LibraryHelper::GetClasses($namespace, $namespaceDirectory, $ignored);
			$injectables = LibraryHelper::GetClassesByAttribute($classes, $injectableAttributeName);

			foreach ($injectables as $injectable) {
				$injectableAttribute = array_filter((new \ReflectionClass($injectable))->getAttributes(), function($attr) use ($injectableAttributeName) {
					return $attr->getName() == $injectableAttributeName;
				})[0];
				$injectableAttribute->newInstance();
			}
		}

		#endregion
	}
}
?>