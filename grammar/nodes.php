<?php declare(strict_types=1);

/**
 * Schema of the slots of the node classes in src/Nodes/**: build.php generates from it the Slots constant
 * and the constructor of every class, and src/LayoutData.php. Everything else in
 * a node class (docblock, parent, interfaces, methods) is handwritten.
 *
 * Key: class relative to PhpSyntax\Nodes. Slots are listed in source order; a slot type is Token or a node class
 * (relative to PhpSyntax\Nodes, or one of the base classes in PhpSyntax), optionally nullable (?), a union (A|B),
 * or a list (NodeList<T>, SeparatedNodeList<T>). A new class is written with the parent of its directory: Expression
 * extends ExpressionNode, Scalar extends ScalarNode, Statement extends StatementNode, Type extends TypeNode, Member extends MemberNode,
 * the rest Node.
 * 'manual' marks a class whose constructor and Slots are handwritten too; the schema only documents it.
 * 'layout' gives the LayoutRole of a slot where it differs from the convention (a close* slot and endKeyword
 * close the construct, open*, *Keyword, modifiers, attributes and semicolon stand where it stands, body is its
 * body, everything else is its content).
 */

return [
	// ---------- names, identifiers, arguments, parameters ----------

	'NameNode' => [
		'slots' => ['token' => 'Token'],
	],
	'IdentifierNode' => [
		'slots' => ['token' => 'Token'],
	],
	'ArgumentListNode' => [
		'slots' => [
			'openParen' => 'Token',
			'items' => 'SeparatedNodeList<ArgumentNode|VariadicPlaceholderNode|ArgumentPlaceholderNode>',
			'closeParen' => 'Token',
		],
	],
	'ArgumentNode' => [
		'slots' => [
			'name' => '?IdentifierNode',
			'colon' => '?Token',
			'ampersand' => '?Token',
			'ellipsis' => '?Token',
			'value' => 'ExpressionNode',
		],
	],
	'VariadicPlaceholderNode' => [
		'slots' => ['ellipsis' => 'Token'],
	],
	'ArgumentPlaceholderNode' => [
		'slots' => [
			'name' => '?IdentifierNode',
			'colon' => '?Token',
			'question' => 'Token',
		],
	],
	'AttributeGroupNode' => [
		'slots' => [
			'openAttribute' => 'Token',
			'attributes' => 'SeparatedNodeList<AttributeNode>',
			'closeBracket' => 'Token',
		],
		'layout' => ['attributes' => 'Content'],
	],
	'AttributeNode' => [
		'slots' => [
			'name' => 'NameNode',
			'arguments' => '?ArgumentListNode',
		],
	],
	'ModifiersNode' => [
		'manual' => true,
		'slots' => ['tokens' => 'list<Token>'],
		'layout' => ['tokens' => 'Anchor'],
	],
	'NodeList' => [
		'manual' => true,
		'slots' => ['items' => 'list<Node>'],
	],
	'SeparatedNodeList' => [
		'manual' => true,
		'slots' => ['items' => 'list<Node>', 'separators' => 'list<Token>'],
	],
	'FileNode' => [
		'manual' => true,
		'slots' => [
			'statements' => 'NodeList<StatementNode>',
			'endOfFile' => 'Token',
		],
		'layout' => ['statements' => 'Anchor', 'endOfFile' => 'Anchor'],
	],
	// ---------- scalars ----------

	'Scalar\IntegerNode' => [
		'slots' => ['token' => 'Token'],
	],
	'Scalar\FloatNode' => [
		'slots' => ['token' => 'Token'],
	],
	'Scalar\BooleanNode' => [
		'slots' => ['token' => 'Token'],
	],
	'Scalar\NullNode' => [
		'slots' => ['token' => 'Token'],
	],
	'Scalar\StringNode' => [
		'slots' => ['token' => 'Token'],
	],
	'Scalar\UnquotedStringNode' => [
		'slots' => ['token' => 'Token'],
	],
	'Scalar\MagicConstantNode' => [
		'slots' => ['token' => 'Token'],
	],
	'Scalar\InterpolatedStringNode' => [
		'slots' => [
			'openQuote' => 'Token',
			'parts' => 'NodeList<Scalar\InterpolatedStringPartNode|Scalar\InterpolationNode|ExpressionNode>',
			'closeQuote' => 'Token',
		],
	],
	'Scalar\HeredocNode' => [
		'slots' => [
			'openDelimiter' => 'Token',
			'parts' => 'NodeList<Scalar\InterpolatedStringPartNode|Scalar\InterpolationNode|ExpressionNode>',
			'closeDelimiter' => 'Token',
		],
	],
	'Scalar\InterpolatedStringPartNode' => [
		'slots' => ['token' => 'Token'],
	],
	'Scalar\InterpolationNode' => [
		'slots' => [
			'openBrace' => 'Token',
			'expression' => 'ExpressionNode',
			'closeBrace' => 'Token',
		],
	],

	// ---------- types ----------

	'Type\NamedTypeNode' => [
		'slots' => ['name' => 'NameNode'],
	],
	'Type\NullableTypeNode' => [
		'slots' => [
			'question' => 'Token',
			'type' => 'TypeNode',
		],
	],
	'Type\UnionTypeNode' => [
		'slots' => ['types' => 'SeparatedNodeList<TypeNode>'],
		'layout' => ['types' => 'Anchor'],
	],
	'Type\IntersectionTypeNode' => [
		'slots' => [
			'openParen' => '?Token',
			'types' => 'SeparatedNodeList<TypeNode>',
			'closeParen' => '?Token',
		],
		'layout' => ['types' => 'Anchor'],
	],
];
