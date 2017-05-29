<?php
use Symfony\Component\Yaml\Yaml;

function yaml_parse_file($file)
{
	try
	{
		$data = @file_get_contents($file);
		if ($data === false)
		{
			return false;
		}
		$data = Yaml::parse($data);
	}
	catch (Exception $e)
	{
		return false;
	}
	return $data;
}

function yaml_emit_file($file, $data)
{
	$data = Yaml::dump($data);
	if (@file_put_contents($file, $data) === false)
	{
		return false;
	}
	return true;
}
