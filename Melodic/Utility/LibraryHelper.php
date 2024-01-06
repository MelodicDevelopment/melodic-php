<?php
namespace Melodic\Utility
{
	class LibraryHelper
	{
		public static function GetClasses(string $namespace, string $namespaceDirectory, array $ignored = []): array
		{
			$classes = [];

			$strtolower = function (string $item) {
				return strtolower($item);
			};
			$ignored = array_map($strtolower, $ignored);
			$directoryItems = array_map($strtolower, scandir($namespaceDirectory));
			
			$namespaceItems = array_diff(
				array_filter($directoryItems, function ($item) use ($namespaceDirectory) {
					$item = strtolower($item);
					return str_contains($item, ".php") || is_dir($namespaceDirectory."/".$item);
				}
			), $ignored);

			foreach($namespaceItems as &$item) {
				if (is_dir($namespaceDirectory."/".$item)) {
					$classes = array_merge($classes, LibraryHelper::GetClasses($namespace."\\".$item, $namespaceDirectory."/".$item, $ignored));
				} else {
					$possibleClassFilePath = realpath($namespaceDirectory."/".$item);
					$possibleClass = $namespace."\\".str_replace(".php", "", $item);

					if (!in_array($possibleClassFilePath, $ignored) && class_exists($possibleClass, true)) {
						array_push($classes, $possibleClass);
					}
				}
			}

			return $classes;
		}

		public static function GetClassesByAttribute(array $classes, string $attributeName)
		{
			$classesByAttribute = [];

			foreach ($classes as $class) {
				$reflectionClass = new \ReflectionClass($class);
				$attributes = $reflectionClass->getAttributes();

				$attributeSearch = function(\ReflectionAttribute $attr) {
					return $attr->getName();
				};

				if (in_array($attributeName, array_map($attributeSearch, $attributes))) {
					array_push($classesByAttribute,$class );
				}
			}

			return $classesByAttribute;
		}
	}
}
?>