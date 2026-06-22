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

	// ---------- items of lists ----------

	'ArrayItemNode' => [
		'slots' => [
			'key' => '?ExpressionNode',
			'doubleArrow' => '?Token',
			'ampersand' => '?Token',
			'ellipsis' => '?Token',
			'value' => 'ExpressionNode|Expression\ListNode',
		],
	],
	'EmptyArrayItemNode' => [
		'slots' => [],
	],
	'MatchArmNode' => [
		'slots' => [
			'values' => '?SeparatedNodeList<ExpressionNode>',
			'defaultKeyword' => '?Token',
			'defaultComma' => '?Token',
			'doubleArrow' => 'Token',
			'body' => 'ExpressionNode',
		],
		'layout' => ['values' => 'Anchor', 'body' => 'Content'],
	],

	// ---------- expressions ----------

	'Expression\VariableNode' => [
		'slots' => [
			'dollar' => '?Token',
			'openBrace' => '?Token',
			'name' => 'Token|ExpressionNode',
			'closeBrace' => '?Token',
		],
	],
	'Expression\ArrayAccessNode' => [
		'slots' => [
			'expression' => 'ExpressionNode',
			'openBracket' => 'Token',
			'index' => '?ExpressionNode',
			'closeBracket' => 'Token',
		],
	],
	'Expression\PropertyFetchNode' => [
		'slots' => [
			'object' => 'ExpressionNode',
			'operator' => 'Token',
			'openBrace' => '?Token',
			'name' => 'IdentifierNode|ExpressionNode',
			'closeBrace' => '?Token',
		],
		'layout' => ['operator' => 'Link'],
	],
	'Expression\StaticPropertyFetchNode' => [
		'slots' => [
			'class' => 'NameNode|ExpressionNode',
			'doubleColon' => 'Token',
			'dollar' => '?Token',
			'openBrace' => '?Token',
			'name' => 'Token|ExpressionNode',
			'closeBrace' => '?Token',
		],
	],
	'Expression\ClassConstantFetchNode' => [
		'slots' => [
			'class' => 'NameNode|ExpressionNode',
			'doubleColon' => 'Token',
			'openBrace' => '?Token',
			'name' => 'IdentifierNode|ExpressionNode',
			'closeBrace' => '?Token',
		],
	],
	'Expression\ConstantFetchNode' => [
		'slots' => ['name' => 'NameNode'],
	],
	'Expression\FunctionCallNode' => [
		'slots' => [
			'name' => 'NameNode|ExpressionNode',
			'arguments' => 'ArgumentListNode',
		],
	],
	'Expression\MethodCallNode' => [
		'slots' => [
			'object' => 'ExpressionNode',
			'operator' => 'Token',
			'openBrace' => '?Token',
			'name' => 'IdentifierNode|ExpressionNode',
			'closeBrace' => '?Token',
			'arguments' => 'ArgumentListNode',
		],
		'layout' => ['operator' => 'Link'],
	],
	'Expression\StaticMethodCallNode' => [
		'slots' => [
			'class' => 'NameNode|ExpressionNode',
			'doubleColon' => 'Token',
			'openBrace' => '?Token',
			'name' => 'IdentifierNode|ExpressionNode',
			'closeBrace' => '?Token',
			'arguments' => 'ArgumentListNode',
		],
	],
	'Expression\ArrayNode' => [
		'slots' => [
			'arrayKeyword' => '?Token',
			'openDelimiter' => 'Token',
			'items' => 'SeparatedNodeList<ArrayItemNode|EmptyArrayItemNode>',
			'closeDelimiter' => 'Token',
		],
	],
	'Expression\ListNode' => [
		'slots' => [
			'listKeyword' => '?Token',
			'openDelimiter' => 'Token',
			'items' => 'SeparatedNodeList<ArrayItemNode|EmptyArrayItemNode>',
			'closeDelimiter' => 'Token',
		],
	],
	'Expression\AssignmentNode' => [
		'slots' => [
			'target' => 'ExpressionNode|Expression\ListNode',
			'operator' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\CombinedAssignmentNode' => [
		'slots' => [
			'target' => 'ExpressionNode',
			'operator' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\AssignmentByReferenceNode' => [
		'slots' => [
			'target' => 'ExpressionNode',
			'equals' => 'Token',
			'ampersand' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\BinaryOpNode' => [
		'slots' => [
			'left' => 'ExpressionNode',
			'operator' => 'Token',
			'right' => 'ExpressionNode',
		],
		'layout' => ['operator' => 'Operator', 'right' => 'Operator'],
	],
	'Expression\UnaryOpNode' => [
		'slots' => [
			'operator' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\PrefixOpNode' => [
		'slots' => [
			'operator' => 'Token',
			'target' => 'ExpressionNode',
		],
	],
	'Expression\PostfixOpNode' => [
		'slots' => [
			'target' => 'ExpressionNode',
			'operator' => 'Token',
		],
	],
	'Expression\CastNode' => [
		'slots' => [
			'cast' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\TernaryNode' => [
		'slots' => [
			'condition' => 'ExpressionNode',
			'question' => 'Token',
			'then' => '?ExpressionNode',
			'colon' => 'Token',
			'else' => 'ExpressionNode',
		],
		'layout' => ['question' => 'Branch', 'then' => 'Branch', 'colon' => 'Branch', 'else' => 'Branch'],
	],
	'Expression\InstanceofNode' => [
		'slots' => [
			'expression' => 'ExpressionNode',
			'instanceofKeyword' => 'Token',
			'class' => 'NameNode|ExpressionNode',
		],
		'layout' => ['instanceofKeyword' => 'Operator', 'class' => 'Operator'],
	],
	'Expression\ParenthesizedNode' => [
		'slots' => [
			'openParen' => 'Token',
			'expression' => 'ExpressionNode',
			'closeParen' => 'Token',
		],
	],
	'Expression\IssetNode' => [
		'slots' => [
			'issetKeyword' => 'Token',
			'openParen' => 'Token',
			'variables' => 'SeparatedNodeList<ExpressionNode>',
			'closeParen' => 'Token',
		],
	],
	'Expression\EmptyNode' => [
		'slots' => [
			'emptyKeyword' => 'Token',
			'openParen' => 'Token',
			'expression' => 'ExpressionNode',
			'closeParen' => 'Token',
		],
	],
	'Expression\EvalNode' => [
		'slots' => [
			'evalKeyword' => 'Token',
			'openParen' => 'Token',
			'expression' => 'ExpressionNode',
			'closeParen' => 'Token',
		],
	],
	'Expression\IncludeNode' => [
		'slots' => [
			'includeKeyword' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\ExitNode' => [
		'slots' => [
			'exitKeyword' => 'Token',
			'arguments' => '?ArgumentListNode',
		],
	],
	'Expression\PrintNode' => [
		'slots' => [
			'printKeyword' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\YieldNode' => [
		'slots' => [
			'yieldKeyword' => 'Token',
			'key' => '?ExpressionNode',
			'doubleArrow' => '?Token',
			'value' => '?ExpressionNode',
		],
	],
	'Expression\YieldFromNode' => [
		'slots' => [
			'yieldFromKeyword' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\ThrowNode' => [
		'slots' => [
			'throwKeyword' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\CloneNode' => [
		'slots' => [
			'cloneKeyword' => 'Token',
			'expression' => 'ExpressionNode',
		],
	],
	'Expression\MatchNode' => [
		'slots' => [
			'matchKeyword' => 'Token',
			'openParen' => 'Token',
			'subject' => 'ExpressionNode',
			'closeParen' => 'Token',
			'openBrace' => 'Token',
			'arms' => 'SeparatedNodeList<MatchArmNode>',
			'closeBrace' => 'Token',
		],
	],
	'Expression\ShellExecNode' => [
		'slots' => [
			'openBacktick' => 'Token',
			'parts' => 'NodeList<Scalar\InterpolatedStringPartNode|Scalar\InterpolationNode|ExpressionNode>',
			'closeBacktick' => 'Token',
		],
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
