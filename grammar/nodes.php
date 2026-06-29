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
			'items' => 'SeparatedNodeList<ArgumentNode|VariadicPlaceholderNode>',
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
	'ParameterNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'modifiers' => 'ModifiersNode',
			'type' => '?TypeNode',
			'ampersand' => '?Token',
			'ellipsis' => '?Token',
			'variable' => 'Expression\VariableNode',
			'equals' => '?Token',
			'default' => '?ExpressionNode',
			'openBrace' => '?Token',
			'hooks' => '?NodeList<Member\PropertyHookNode>',
			'closeBrace' => '?Token',
		],
		'layout' => ['type' => 'Anchor', 'ampersand' => 'Anchor', 'ellipsis' => 'Anchor', 'variable' => 'Anchor'],
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
	'AnonymousClassNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'modifiers' => 'ModifiersNode',
			'classKeyword' => 'Token',
			'arguments' => '?ArgumentListNode',
			'extendsKeyword' => '?Token',
			'extends' => '?NameNode',
			'implementsKeyword' => '?Token',
			'implements' => '?SeparatedNodeList<NameNode>',
			'openBrace' => 'Token',
			'members' => 'NodeList<MemberNode>',
			'closeBrace' => 'Token',
		],
		'layout' => ['extendsKeyword' => 'Content', 'implementsKeyword' => 'Content'],
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
	'ClosureUsesNode' => [
		'slots' => [
			'useKeyword' => 'Token',
			'openParen' => 'Token',
			'variables' => 'SeparatedNodeList<ClosureUseNode>',
			'closeParen' => 'Token',
		],
	],
	'ClosureUseNode' => [
		'slots' => [
			'ampersand' => '?Token',
			'variable' => 'Expression\VariableNode',
		],
	],
	'ElseIfNode' => [
		'slots' => [
			'elseifKeyword' => 'Token',
			'openParen' => 'Token',
			'condition' => 'ExpressionNode',
			'closeParen' => 'Token',
			'body' => '?StatementNode',
			'colon' => '?Token',
			'statements' => '?NodeList<StatementNode>',
		],
	],
	'ElseNode' => [
		'slots' => [
			'elseKeyword' => 'Token',
			'body' => '?StatementNode',
			'colon' => '?Token',
			'statements' => '?NodeList<StatementNode>',
		],
	],
	'CaseNode' => [
		'slots' => [
			'caseKeyword' => 'Token',
			'value' => '?ExpressionNode',
			'separator' => 'Token',
			'statements' => 'NodeList<StatementNode>',
		],
	],
	'CatchNode' => [
		'slots' => [
			'catchKeyword' => 'Token',
			'openParen' => 'Token',
			'types' => 'SeparatedNodeList<NameNode>',
			'variable' => '?Expression\VariableNode',
			'closeParen' => 'Token',
			'body' => 'Statement\BlockNode',
		],
	],
	'FinallyNode' => [
		'slots' => [
			'finallyKeyword' => 'Token',
			'body' => 'Statement\BlockNode',
		],
	],
	'DeclareItemNode' => [
		'slots' => [
			'name' => 'IdentifierNode',
			'equals' => 'Token',
			'value' => 'ExpressionNode',
		],
	],
	'StaticVariableNode' => [
		'slots' => [
			'variable' => 'Expression\VariableNode',
			'equals' => '?Token',
			'default' => '?ExpressionNode',
		],
	],
	'UseItemNode' => [
		'slots' => [
			'type' => '?Token',
			'name' => 'NameNode',
			'asKeyword' => '?Token',
			'alias' => '?IdentifierNode',
		],
		'layout' => ['asKeyword' => 'Content'],
	],
	'ConstItemNode' => [
		'slots' => [
			'name' => 'IdentifierNode',
			'equals' => 'Token',
			'value' => 'ExpressionNode',
		],
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
	'Expression\NewNode' => [
		'slots' => [
			'newKeyword' => 'Token',
			'class' => 'NameNode|ExpressionNode|AnonymousClassNode',
			'arguments' => '?ArgumentListNode',
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
	'Expression\ClosureNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'staticKeyword' => '?Token',
			'functionKeyword' => 'Token',
			'ampersand' => '?Token',
			'openParen' => 'Token',
			'parameters' => 'SeparatedNodeList<ParameterNode>',
			'closeParen' => 'Token',
			'uses' => '?ClosureUsesNode',
			'colon' => '?Token',
			'returnType' => '?TypeNode',
			'body' => 'Statement\BlockNode',
		],
	],
	'Expression\ArrowFunctionNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'staticKeyword' => '?Token',
			'fnKeyword' => 'Token',
			'ampersand' => '?Token',
			'openParen' => 'Token',
			'parameters' => 'SeparatedNodeList<ParameterNode>',
			'closeParen' => 'Token',
			'colon' => '?Token',
			'returnType' => '?TypeNode',
			'doubleArrow' => 'Token',
			'expression' => 'ExpressionNode',
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

	// ---------- statements ----------

	'Statement\NamespaceNode' => [
		'slots' => [
			'namespaceKeyword' => 'Token',
			'name' => '?NameNode',
			'semicolon' => '?Token',
			'openBrace' => '?Token',
			'statements' => 'NodeList<StatementNode>',
			'closeBrace' => '?Token',
		],
	],
	'Statement\UseNode' => [
		'slots' => [
			'useKeyword' => 'Token',
			'type' => '?Token',
			'prefix' => '?NameNode',
			'namespaceSeparator' => '?Token',
			'openBrace' => '?Token',
			'items' => 'SeparatedNodeList<UseItemNode>',
			'closeBrace' => '?Token',
			'semicolon' => 'Token',
		],
	],
	'Statement\ConstNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'constKeyword' => 'Token',
			'items' => 'SeparatedNodeList<ConstItemNode>',
			'semicolon' => 'Token',
		],
	],
	'Statement\HaltCompilerNode' => [
		'slots' => [
			'haltKeyword' => 'Token',
			'openParen' => 'Token',
			'closeParen' => 'Token',
			'semicolon' => 'Token',
			'data' => '?Token',
		],
	],
	'Statement\InlineHtmlNode' => [
		'slots' => ['html' => 'Token'],
	],
	'Statement\EmptyStatementNode' => [
		'slots' => ['semicolon' => 'Token'],
	],
	'Statement\ExpressionStatementNode' => [
		'slots' => [
			'expression' => 'ExpressionNode',
			'semicolon' => 'Token',
		],
	],
	'Statement\BlockNode' => [
		'slots' => [
			'openBrace' => 'Token',
			'statements' => 'NodeList<StatementNode>',
			'closeBrace' => 'Token',
		],
	],
	'Statement\IfNode' => [
		'slots' => [
			'ifKeyword' => 'Token',
			'openParen' => 'Token',
			'condition' => 'ExpressionNode',
			'closeParen' => 'Token',
			'body' => '?StatementNode',
			'colon' => '?Token',
			'statements' => '?NodeList<StatementNode>',
			'elseifs' => 'NodeList<ElseIfNode>',
			'else' => '?ElseNode',
			'endKeyword' => '?Token',
			'semicolon' => '?Token',
		],
		'layout' => ['elseifs' => 'Anchor', 'else' => 'Anchor'],
	],
	'Statement\WhileNode' => [
		'slots' => [
			'whileKeyword' => 'Token',
			'openParen' => 'Token',
			'condition' => 'ExpressionNode',
			'closeParen' => 'Token',
			'body' => '?StatementNode',
			'colon' => '?Token',
			'statements' => '?NodeList<StatementNode>',
			'endKeyword' => '?Token',
			'semicolon' => '?Token',
		],
	],
	'Statement\DoWhileNode' => [
		'slots' => [
			'doKeyword' => 'Token',
			'body' => 'StatementNode',
			'whileKeyword' => 'Token',
			'openParen' => 'Token',
			'condition' => 'ExpressionNode',
			'closeParen' => 'Token',
			'semicolon' => 'Token',
		],
	],
	'Statement\ForNode' => [
		'slots' => [
			'forKeyword' => 'Token',
			'openParen' => 'Token',
			'initializers' => 'SeparatedNodeList<ExpressionNode>',
			'firstSemicolon' => 'Token',
			'conditions' => 'SeparatedNodeList<ExpressionNode>',
			'secondSemicolon' => 'Token',
			'updates' => 'SeparatedNodeList<ExpressionNode>',
			'closeParen' => 'Token',
			'body' => '?StatementNode',
			'colon' => '?Token',
			'statements' => '?NodeList<StatementNode>',
			'endKeyword' => '?Token',
			'semicolon' => '?Token',
		],
	],
	'Statement\ForeachNode' => [
		'slots' => [
			'foreachKeyword' => 'Token',
			'openParen' => 'Token',
			'expression' => 'ExpressionNode',
			'asKeyword' => 'Token',
			'key' => '?ExpressionNode',
			'doubleArrow' => '?Token',
			'ampersand' => '?Token',
			'value' => 'ExpressionNode|Expression\ListNode',
			'closeParen' => 'Token',
			'body' => '?StatementNode',
			'colon' => '?Token',
			'statements' => '?NodeList<StatementNode>',
			'endKeyword' => '?Token',
			'semicolon' => '?Token',
		],
		'layout' => ['asKeyword' => 'Content'],
	],
	'Statement\SwitchNode' => [
		'slots' => [
			'switchKeyword' => 'Token',
			'openParen' => 'Token',
			'subject' => 'ExpressionNode',
			'closeParen' => 'Token',
			'openBrace' => '?Token',
			'colon' => '?Token',
			'leadingSemicolon' => '?Token',
			'cases' => 'NodeList<CaseNode>',
			'closeBrace' => '?Token',
			'endKeyword' => '?Token',
			'semicolon' => '?Token',
		],
		'layout' => ['cases' => 'Case'],
	],
	'Statement\BreakNode' => [
		'slots' => [
			'breakKeyword' => 'Token',
			'expression' => '?ExpressionNode',
			'semicolon' => 'Token',
		],
	],
	'Statement\ContinueNode' => [
		'slots' => [
			'continueKeyword' => 'Token',
			'expression' => '?ExpressionNode',
			'semicolon' => 'Token',
		],
	],
	'Statement\ReturnNode' => [
		'slots' => [
			'returnKeyword' => 'Token',
			'expression' => '?ExpressionNode',
			'semicolon' => 'Token',
		],
	],
	'Statement\GlobalNode' => [
		'slots' => [
			'globalKeyword' => 'Token',
			'variables' => 'SeparatedNodeList<ExpressionNode>',
			'semicolon' => 'Token',
		],
	],
	'Statement\StaticNode' => [
		'slots' => [
			'staticKeyword' => 'Token',
			'variables' => 'SeparatedNodeList<StaticVariableNode>',
			'semicolon' => 'Token',
		],
	],
	'Statement\EchoNode' => [
		'slots' => [
			'echoKeyword' => 'Token',
			'expressions' => 'SeparatedNodeList<ExpressionNode>',
			'semicolon' => 'Token',
		],
	],
	'Statement\UnsetNode' => [
		'slots' => [
			'unsetKeyword' => 'Token',
			'openParen' => 'Token',
			'variables' => 'SeparatedNodeList<ExpressionNode>',
			'closeParen' => 'Token',
			'semicolon' => 'Token',
		],
	],
	'Statement\DeclareNode' => [
		'slots' => [
			'declareKeyword' => 'Token',
			'openParen' => 'Token',
			'items' => 'SeparatedNodeList<DeclareItemNode>',
			'closeParen' => 'Token',
			'body' => '?StatementNode',
			'colon' => '?Token',
			'statements' => '?NodeList<StatementNode>',
			'endKeyword' => '?Token',
			'semicolon' => '?Token',
		],
	],
	'Statement\TryNode' => [
		'slots' => [
			'tryKeyword' => 'Token',
			'body' => 'Statement\BlockNode',
			'catches' => 'NodeList<CatchNode>',
			'finally' => '?FinallyNode',
		],
		'layout' => ['catches' => 'Anchor', 'finally' => 'Anchor'],
	],
	'Statement\GotoNode' => [
		'slots' => [
			'gotoKeyword' => 'Token',
			'label' => 'IdentifierNode',
			'semicolon' => 'Token',
		],
	],
	'Statement\LabelNode' => [
		'slots' => [
			'name' => 'IdentifierNode',
			'colon' => 'Token',
		],
	],
	'Statement\FunctionNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'functionKeyword' => 'Token',
			'ampersand' => '?Token',
			'name' => 'IdentifierNode',
			'openParen' => 'Token',
			'parameters' => 'SeparatedNodeList<ParameterNode>',
			'closeParen' => 'Token',
			'colon' => '?Token',
			'returnType' => '?TypeNode',
			'body' => 'Statement\BlockNode',
		],
	],
	'Statement\ClassNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'modifiers' => 'ModifiersNode',
			'classKeyword' => 'Token',
			'name' => 'IdentifierNode',
			'extendsKeyword' => '?Token',
			'extends' => '?NameNode',
			'implementsKeyword' => '?Token',
			'implements' => '?SeparatedNodeList<NameNode>',
			'openBrace' => 'Token',
			'members' => 'NodeList<MemberNode>',
			'closeBrace' => 'Token',
		],
		'layout' => ['extendsKeyword' => 'Content', 'implementsKeyword' => 'Content'],
	],
	'Statement\InterfaceNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'interfaceKeyword' => 'Token',
			'name' => 'IdentifierNode',
			'extendsKeyword' => '?Token',
			'extends' => '?SeparatedNodeList<NameNode>',
			'openBrace' => 'Token',
			'members' => 'NodeList<MemberNode>',
			'closeBrace' => 'Token',
		],
		'layout' => ['extendsKeyword' => 'Content'],
	],
	'Statement\TraitNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'traitKeyword' => 'Token',
			'name' => 'IdentifierNode',
			'openBrace' => 'Token',
			'members' => 'NodeList<MemberNode>',
			'closeBrace' => 'Token',
		],
	],
	'Statement\EnumNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'enumKeyword' => 'Token',
			'name' => 'IdentifierNode',
			'colon' => '?Token',
			'scalarType' => '?TypeNode',
			'implementsKeyword' => '?Token',
			'implements' => '?SeparatedNodeList<NameNode>',
			'openBrace' => 'Token',
			'members' => 'NodeList<MemberNode>',
			'closeBrace' => 'Token',
		],
		'layout' => ['implementsKeyword' => 'Content'],
	],

	// ---------- members ----------

	'Member\PropertyNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'modifiers' => 'ModifiersNode',
			'type' => '?TypeNode',
			'items' => 'SeparatedNodeList<Member\PropertyItemNode>',
			'semicolon' => '?Token',
			'openBrace' => '?Token',
			'hooks' => '?NodeList<Member\PropertyHookNode>',
			'closeBrace' => '?Token',
		],
		'layout' => ['type' => 'Anchor'],
	],
	'Member\PropertyItemNode' => [
		'slots' => [
			'name' => 'Token',
			'equals' => '?Token',
			'default' => '?ExpressionNode',
		],
	],
	'Member\PropertyHookNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'modifiers' => 'ModifiersNode',
			'ampersand' => '?Token',
			'name' => 'IdentifierNode',
			'openParen' => '?Token',
			'parameters' => '?SeparatedNodeList<ParameterNode>',
			'closeParen' => '?Token',
			'body' => '?Statement\BlockNode',
			'doubleArrow' => '?Token',
			'expression' => '?ExpressionNode',
			'semicolon' => '?Token',
		],
		'layout' => ['ampersand' => 'Anchor', 'name' => 'Anchor'],
	],
	'Member\ClassConstNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'modifiers' => 'ModifiersNode',
			'constKeyword' => 'Token',
			'type' => '?TypeNode',
			'items' => 'SeparatedNodeList<ConstItemNode>',
			'semicolon' => 'Token',
		],
		'layout' => ['type' => 'Anchor'],
	],
	'Member\MethodNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'modifiers' => 'ModifiersNode',
			'functionKeyword' => 'Token',
			'ampersand' => '?Token',
			'name' => 'IdentifierNode',
			'openParen' => 'Token',
			'parameters' => 'SeparatedNodeList<ParameterNode>',
			'closeParen' => 'Token',
			'colon' => '?Token',
			'returnType' => '?TypeNode',
			'body' => '?Statement\BlockNode',
			'semicolon' => '?Token',
		],
	],
	'Member\TraitUseNode' => [
		'slots' => [
			'useKeyword' => 'Token',
			'traits' => 'SeparatedNodeList<NameNode>',
			'semicolon' => '?Token',
			'openBrace' => '?Token',
			'adaptations' => '?NodeList<Member\TraitAdaptationNode>',
			'closeBrace' => '?Token',
		],
	],
	'Member\TraitPrecedenceNode' => [
		'slots' => [
			'trait' => 'NameNode',
			'doubleColon' => 'Token',
			'method' => 'IdentifierNode',
			'insteadofKeyword' => 'Token',
			'traits' => 'SeparatedNodeList<NameNode>',
			'semicolon' => 'Token',
		],
		'layout' => ['insteadofKeyword' => 'Content'],
	],
	'Member\TraitAliasNode' => [
		'slots' => [
			'trait' => '?NameNode',
			'doubleColon' => '?Token',
			'method' => 'IdentifierNode',
			'asKeyword' => 'Token',
			'modifier' => '?Token',
			'alias' => '?IdentifierNode',
			'semicolon' => 'Token',
		],
		'layout' => ['asKeyword' => 'Content'],
	],
	'Member\EnumCaseNode' => [
		'slots' => [
			'attributes' => 'NodeList<AttributeGroupNode>',
			'caseKeyword' => 'Token',
			'name' => 'IdentifierNode',
			'equals' => '?Token',
			'value' => '?ExpressionNode',
			'semicolon' => 'Token',
		],
	],
];
