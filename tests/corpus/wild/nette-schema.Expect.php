<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Schema;

use Nette;
use Nette\Schema\Elements\AnyOf;
use Nette\Schema\Elements\ArrayType;
use Nette\Schema\Elements\EnumType;
use Nette\Schema\Elements\NumberType;
use Nette\Schema\Elements\StringType;
use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use function count, is_object, is_string;


/**
 * Schema generator.
 *
 * @method static Type scalar($default = null)
 * @method static StringType string($default = null)
 * @method static NumberType int($default = null)
 * @method static NumberType float($default = null)
 * @method static Type bool($default = null)
 * @method static Type null()
 * @method static ArrayType list($default = [])
 * @method static Type mixed($default = null)
 * @method static StringType email($default = null)
 * @method static StringType unicode($default = null)
 */
final class Expect
{
	/** @param  list<mixed>  $args */
	public static function __callStatic(string $name, array $args): Type
	{
		$type = self::type($name);
		if ($args) {
			$type->default($args[0]);
		}

		return $type;
	}


	/**
	 * Creates a schema for a type expression (e.g., 'int|string', 'null|float', 'email'); an expression
	 * of a single kind of value gets its dedicated StringType, NumberType, ArrayType or EnumType.
	 */
	public static function type(string $type): Type
	{
		$item = TypeExpression::parse($type);
		if ($item['kind'] === Kind::Instance && is_subclass_of($item['type'], \BackedEnum::class)) {
			return new EnumType($type);
		}

		$variants = $item['kind'] === Kind::Union ? $item['variants'] : [$item];
		$classes = array_unique(array_map(fn(array $variant) => match ($variant['kind']) {
			Kind::String => StringType::class,
			Kind::Int, Kind::Float, Kind::Number => NumberType::class,
			Kind::Array, Kind::List, Kind::Iterable => ArrayType::class,
			default => Type::class,
		}, $variants));
		$class = count($classes) === 1 ? reset($classes) : Type::class;
		return new $class($type);
	}


	/**
	 * Creates a schema for a backed enum: a case or its backing value is accepted, the case is returned.
	 * @param  class-string<\BackedEnum>  $enum
	 */
	public static function enum(string $enum): EnumType
	{
		return new EnumType($enum);
	}


	/**
	 * Creates a union schema that accepts any of the given values or sub-schemas.
	 */
	public static function anyOf(mixed ...$set): AnyOf
	{
		return new AnyOf(...$set);
	}


	/**
	 * Creates a structure schema with defined properties; output is stdClass.
	 * @param  Schema[]  $shape
	 */
	public static function structure(array $shape): Structure
	{
		return new Structure($shape);
	}


	/**
	 * Generates a structure schema from a class instance by reflecting its properties or constructor parameters.
	 * @param  array<string, Schema>  $items  Optional overrides for specific properties.
	 */
	public static function from(object $object, array $items = []): Structure
	{
		$ro = new \ReflectionObject($object);
		$props = $ro->hasMethod('__construct')
			? $ro->getMethod('__construct')->getParameters()
			: $ro->getProperties();

		foreach ($props as $prop) {
			$name = $prop->getName();
			if (!isset($items[$name])) {
				$type = Helpers::getPropertyType($prop) ?? 'mixed';
				$item = self::type($type);
				if ($prop instanceof \ReflectionProperty ? $prop->isInitialized($object) : $prop->isOptional()) {
					$def = ($prop instanceof \ReflectionProperty ? $prop->getValue($object) : $prop->getDefaultValue());
					if (is_object($def) && !$def instanceof \UnitEnum) {
						$item = static::from($def);
					} elseif ($def === null && !Nette\Utils\Validators::is(null, $type)) {
						$item->required();
					} else {
						$item->default($def);
					}
				} else {
					$item->required();
				}
				$items[$name] = $item;
			}
		}

		return (new Structure($items))->castTo($ro->getName());
	}


	/**
	 * Creates an array schema. When passed Schema elements, behaves like structure() but outputs an array.
	 * Without Schema elements, creates a plain array type with the given default value.
	 * @param  mixed[]  $shape
	 */
	public static function array(?array $shape = []): Structure|ArrayType
	{
		$shape ??= [];
		return Nette\Utils\Arrays::first($shape) instanceof Schema
			? (new Structure($shape))->castTo('array')
			: (new ArrayType('array'))->default($shape);
	}


	/**
	 * Creates an associative or indexed array schema where every value matches the given type.
	 */
	public static function arrayOf(string|Schema $valueType, string|Schema|null $keyType = null): ArrayType
	{
		return (new ArrayType('array'))->items($valueType, $keyType);
	}


	/**
	 * Creates a list schema (sequentially indexed from 0) where every element matches the given type.
	 */
	public static function listOf(string|Schema $type): ArrayType
	{
		return (new ArrayType('list'))->items($type);
	}
}
