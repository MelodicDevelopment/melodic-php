<?php
namespace Melodic\Web
{
	use \Melodic\Config;

	class Response
	{
		private string $_contentType;
		private mixed $_content;
		private array $_headers = array();

		public function __construct()
		{
			$this->_headers = headers_list();
		}

		public function SetContent(mixed $content): void
		{
			$this->_content = $content;
		}

		public function SetContentType(string $contentType): void
		{
			$this->_contentType = $contentType;
			$this->AddHeader("Content-Type", $contentType);
		}

		public function AddHeader(string $header, string $value = null): void
		{
			$this->_headers[$header] = $value;
		}

		public function Render(): void
		{
			foreach ($this->_headers as $header => $value) {
				header("{$header}: {$value}");
			}

			die($this->_content ?? "");
		}
	}
}
?>