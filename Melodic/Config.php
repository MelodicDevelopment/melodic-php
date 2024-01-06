<?php
namespace Melodic
{
	class Config
	{
		public static function Get(string $key, mixed $default = null)
		{
			if (is_readable("config.json")){
				try {
					$contents = json_decode(file_get_contents("config.json"), true);
					
					if (isset($contents[$key])) {
						return $contents[$key];
					}
					
					return $default;
				} catch (\Exception $ex){
					return $default;
				}
			}

			throw new \Exception("No config.json file found.");
		}
	}
}
?>