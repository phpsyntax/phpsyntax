/* Copied from nikic/php-parser grammar/php.y (BSD-3-Clause, https://github.com/nikic/PHP-Parser). */

%pure_parser
%expect 2

%right T_VOID_CAST
%right T_THROW
%left T_INCLUDE T_INCLUDE_ONCE T_EVAL T_REQUIRE T_REQUIRE_ONCE
%left ','
%left T_LOGICAL_OR
%left T_LOGICAL_XOR
%left T_LOGICAL_AND
%right T_PRINT
%right T_YIELD
%right T_DOUBLE_ARROW
%right T_YIELD_FROM
%left '=' T_PLUS_EQUAL T_MINUS_EQUAL T_MUL_EQUAL T_DIV_EQUAL T_CONCAT_EQUAL T_MOD_EQUAL T_AND_EQUAL T_OR_EQUAL T_XOR_EQUAL T_SL_EQUAL T_SR_EQUAL T_POW_EQUAL T_COALESCE_EQUAL
%left '?' ':'
%right T_COALESCE
%left T_BOOLEAN_OR
%left T_BOOLEAN_AND
%left '|'
%left '^'
%left T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
%nonassoc T_IS_EQUAL T_IS_NOT_EQUAL T_IS_IDENTICAL T_IS_NOT_IDENTICAL T_SPACESHIP
%nonassoc '<' T_IS_SMALLER_OR_EQUAL '>' T_IS_GREATER_OR_EQUAL
%left T_PIPE
%left '.'
%left T_SL T_SR
%left '+' '-'
%left '*' '/' '%'
%right '!'
%nonassoc T_INSTANCEOF
%right '~' T_INC T_DEC T_INT_CAST T_DOUBLE_CAST T_STRING_CAST T_ARRAY_CAST T_OBJECT_CAST T_BOOL_CAST T_UNSET_CAST '@'
%right T_POW
%right '['
%nonassoc T_NEW T_CLONE
%token T_EXIT
%token T_IF
%left T_ELSEIF
%left T_ELSE
%left T_ENDIF
%token T_LNUMBER
%token T_DNUMBER
%token T_STRING
%token T_STRING_VARNAME
%token T_VARIABLE
%token T_NUM_STRING
%token T_INLINE_HTML
%token T_ENCAPSED_AND_WHITESPACE
%token T_CONSTANT_ENCAPSED_STRING
%token T_ECHO
%token T_DO
%token T_WHILE
%token T_ENDWHILE
%token T_FOR
%token T_ENDFOR
%token T_FOREACH
%token T_ENDFOREACH
%token T_DECLARE
%token T_ENDDECLARE
%token T_AS
%token T_SWITCH
%token T_MATCH
%token T_ENDSWITCH
%token T_CASE
%token T_DEFAULT
%token T_BREAK
%token T_CONTINUE
%token T_GOTO
%token T_FUNCTION
%token T_FN
%token T_CONST
%token T_RETURN
%token T_TRY
%token T_CATCH
%token T_FINALLY
%token T_THROW
%token T_USE
%token T_INSTEADOF
%token T_GLOBAL
%token T_STATIC T_ABSTRACT T_FINAL T_PRIVATE T_PROTECTED T_PUBLIC T_READONLY
%token T_PUBLIC_SET
%token T_PROTECTED_SET
%token T_PRIVATE_SET
%token T_VAR
%token T_UNSET
%token T_ISSET
%token T_EMPTY
%token T_HALT_COMPILER
%token T_CLASS
%token T_TRAIT
%token T_INTERFACE
%token T_ENUM
%token T_EXTENDS
%token T_IMPLEMENTS
%token T_OBJECT_OPERATOR
%token T_NULLSAFE_OBJECT_OPERATOR
%token T_DOUBLE_ARROW
%token T_LIST
%token T_ARRAY
%token T_CALLABLE
%token T_CLASS_C
%token T_TRAIT_C
%token T_METHOD_C
%token T_FUNC_C
%token T_PROPERTY_C
%token T_LINE
%token T_FILE
%token T_START_HEREDOC
%token T_END_HEREDOC
%token T_DOLLAR_OPEN_CURLY_BRACES
%token T_CURLY_OPEN
%token T_PAAMAYIM_NEKUDOTAYIM
%token T_NAMESPACE
%token T_NS_C
%token T_DIR
%token T_NS_SEPARATOR
%token T_ELLIPSIS
%token T_NAME_FULLY_QUALIFIED
%token T_NAME_QUALIFIED
%token T_NAME_RELATIVE
%token T_ATTRIBUTE
%token T_ENUM

%%

start:
    top_statement_list
;

top_statement_list_ex:
      top_statement_list_ex top_statement
    | /* empty */
;

top_statement_list:
      top_statement_list_ex
;

ampersand:
      T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
    | T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
;

reserved_non_modifiers:
      T_INCLUDE | T_INCLUDE_ONCE | T_EVAL | T_REQUIRE | T_REQUIRE_ONCE | T_LOGICAL_OR | T_LOGICAL_XOR | T_LOGICAL_AND
    | T_INSTANCEOF | T_NEW | T_CLONE | T_EXIT | T_IF | T_ELSEIF | T_ELSE | T_ENDIF | T_DO | T_WHILE
    | T_ENDWHILE | T_FOR | T_ENDFOR | T_FOREACH | T_ENDFOREACH | T_DECLARE | T_ENDDECLARE | T_AS | T_TRY | T_CATCH
    | T_FINALLY | T_THROW | T_USE | T_INSTEADOF | T_GLOBAL | T_VAR | T_UNSET | T_ISSET | T_EMPTY | T_CONTINUE | T_GOTO
    | T_FUNCTION | T_CONST | T_RETURN | T_PRINT | T_YIELD | T_LIST | T_SWITCH | T_ENDSWITCH | T_CASE | T_DEFAULT
    | T_BREAK | T_ARRAY | T_CALLABLE | T_EXTENDS | T_IMPLEMENTS | T_NAMESPACE | T_TRAIT | T_INTERFACE | T_CLASS
    | T_CLASS_C | T_TRAIT_C | T_FUNC_C | T_METHOD_C | T_LINE | T_FILE | T_DIR | T_NS_C | T_FN
    | T_MATCH | T_ENUM
    | T_ECHO
;

semi_reserved:
      reserved_non_modifiers
    | T_STATIC | T_ABSTRACT | T_FINAL | T_PRIVATE | T_PROTECTED | T_PUBLIC | T_READONLY
;

identifier_maybe_reserved:
      T_STRING
    | semi_reserved
;

identifier_not_reserved:
      T_STRING
;

reserved_non_modifiers_identifier:
      reserved_non_modifiers
;

namespace_declaration_name:
      T_STRING
    | semi_reserved
    | T_NAME_QUALIFIED
;

namespace_name:
      T_STRING
    | T_NAME_QUALIFIED
;

legacy_namespace_name:
      namespace_name
    | T_NAME_FULLY_QUALIFIED
;

plain_variable:
      T_VARIABLE
;

semi:
      ';'
;

no_comma:
      /* empty */
    | ','
;

optional_comma:
      /* empty */
    | ','
;

attribute_decl:
      class_name                                            { $$ = Nodes\AttributeNode[$1, null]; }
    | class_name argument_list                              { $$ = Nodes\AttributeNode[$1, $2]; }
;

attribute_group:
      attribute_decl
    | attribute_group ',' attribute_decl
;

attribute:
      T_ATTRIBUTE attribute_group optional_comma ']'        { trailing($2, $3); $$ = Nodes\AttributeGroupNode[$1, $2, $4]; }
;

attributes:
      attribute
    | attributes attribute
;

optional_attributes:
      /* empty */
    | attributes
;

top_statement:
      statement
    | function_declaration_statement
    | class_declaration_statement
    | T_HALT_COMPILER '(' ')' ';'
    | T_NAMESPACE namespace_declaration_name semi
    | T_NAMESPACE namespace_declaration_name '{' top_statement_list '}'
          { $$ = Statement\NamespaceNode[$1, $2, null, $3, $4, $5]; }
    | T_NAMESPACE '{' top_statement_list '}'                { $$ = Statement\NamespaceNode[$1, null, null, $2, $3, $4]; }
    | T_USE use_declarations semi                           { $$ = Statement\UseNode[$1, null, $2, $3]; }
    | T_USE use_type use_declarations semi                  { $$ = Statement\UseNode[$1, $2, $3, $4]; }
    | group_use_declaration
    | T_CONST constant_declaration_list semi                { $$ = Statement\ConstNode[init(), $1, $2, $3]; }
    | attributes T_CONST constant_declaration_list semi     { $$ = Statement\ConstNode[$1, $2, $3, $4]; }
;

use_type:
      T_FUNCTION
    | T_CONST
;

group_use_declaration:
      T_USE use_type legacy_namespace_name T_NS_SEPARATOR '{' unprefixed_use_declarations '}' semi
          { $$ = Statement\GroupUseNode[$1, $2, $3, $4, $5, $6, $7, $8]; }
    | T_USE legacy_namespace_name T_NS_SEPARATOR '{' inline_use_declarations '}' semi
          { $$ = Statement\GroupUseNode[$1, null, $2, $3, $4, $5, $6, $7]; }
;

unprefixed_use_declarations:
      non_empty_unprefixed_use_declarations optional_comma
;

non_empty_unprefixed_use_declarations:
      non_empty_unprefixed_use_declarations ',' unprefixed_use_declaration
    | unprefixed_use_declaration
;

use_declarations:
      non_empty_use_declarations no_comma
;

non_empty_use_declarations:
      non_empty_use_declarations ',' use_declaration
    | use_declaration
;

inline_use_declarations:
      non_empty_inline_use_declarations optional_comma
;

non_empty_inline_use_declarations:
      non_empty_inline_use_declarations ',' inline_use_declaration
    | inline_use_declaration
;

unprefixed_use_declaration:
      namespace_name                                        { $$ = Nodes\UseItemNode[null, $1, null, null]; }
    | namespace_name T_AS identifier_not_reserved           { $$ = Nodes\UseItemNode[null, $1, $2, $3]; }
;

use_declaration:
      legacy_namespace_name                                 { $$ = Nodes\UseItemNode[null, $1, null, null]; }
    | legacy_namespace_name T_AS identifier_not_reserved    { $$ = Nodes\UseItemNode[null, $1, $2, $3]; }
;

inline_use_declaration:
      unprefixed_use_declaration
    | use_type unprefixed_use_declaration                   { $2->type = $1; $$ = $2; }
;

constant_declaration_list:
      non_empty_constant_declaration_list no_comma
;

non_empty_constant_declaration_list:
      non_empty_constant_declaration_list ',' constant_declaration
    | constant_declaration
;

constant_declaration:
    identifier_not_reserved '=' expr                        { $$ = Nodes\ConstItemNode[$1, $2, $3]; }
;

class_const_list:
      non_empty_class_const_list no_comma
;

non_empty_class_const_list:
      non_empty_class_const_list ',' class_const
    | class_const
;

class_const:
      T_STRING '=' expr                                     { $$ = Nodes\ConstItemNode[Nodes\IdentifierNode[$1], $2, $3]; }
    | semi_reserved '=' expr                                { $$ = Nodes\ConstItemNode[Nodes\IdentifierNode[$1], $2, $3]; }
;

inner_statement_list_ex:
      inner_statement_list_ex inner_statement
    | /* empty */
;

inner_statement_list:
      inner_statement_list_ex
;

inner_statement:
      statement
    | function_declaration_statement
    | class_declaration_statement
;

non_empty_statement:
      '{' inner_statement_list '}'                          { $$ = Statement\BlockNode[$1, $2, $3]; }
    | T_IF '(' expr ')' blocklike_statement elseif_list else_single
          { $$ = Statement\IfNode[$1, $2, $3, $4, $5, null, null, $6, $7, null, null]; }
    | T_IF '(' expr ')' ':' inner_statement_list new_elseif_list new_else_single T_ENDIF ';'
          { $$ = Statement\IfNode[$1, $2, $3, $4, null, $5, $6, $7, $8, $9, $10]; }
    | T_WHILE '(' expr ')' while_statement                  { $$ = Statement\WhileNode[$1, $2, $3, $4, ...$5]; }
    | T_DO blocklike_statement T_WHILE '(' expr ')' ';'     { $$ = Statement\DoWhileNode[$1, $2, $3, $4, $5, $6, $7]; }
    | T_FOR '(' for_expr ';'  for_expr ';' for_expr ')' for_statement
    | T_SWITCH '(' expr ')' switch_case_list
    | T_BREAK optional_expr semi
    | T_CONTINUE optional_expr semi
    | T_RETURN optional_expr semi
    | T_GLOBAL global_var_list semi
    | T_STATIC static_var_list semi
    | T_ECHO expr_list_forbid_comma semi
    | T_INLINE_HTML
    | expr semi
    | T_UNSET '(' variables_list ')' semi
    | T_FOREACH '(' expr T_AS foreach_variable ')' foreach_statement
          { $$ = Statement\ForeachNode[$1, $2, $3, $4, null, null, $5[0], $5[1], $6, ...$7]; }
    | T_FOREACH '(' expr T_AS variable T_DOUBLE_ARROW foreach_variable ')' foreach_statement
          { $$ = Statement\ForeachNode[$1, $2, $3, $4, $5, $6, $7[0], $7[1], $8, ...$9]; }
    | T_DECLARE '(' declare_list ')' declare_statement      { $$ = Statement\DeclareNode[$1, $2, $3, $4, ...$5]; }
    | T_TRY '{' inner_statement_list '}' catches optional_finally
          { $$ = Statement\TryNode[$1, Statement\BlockNode[$2, $3, $4], $5, $6]; }
    | T_GOTO identifier_not_reserved semi                   { $$ = Statement\GotoNode[$1, $2, $3]; }
    | identifier_not_reserved ':'                           { $$ = Statement\LabelNode[$1, $2]; }
;

statement:
      non_empty_statement
    | ';'
;

blocklike_statement:
     statement
;

catches:
      /* empty */
    | catches catch
;

name_union:
      name
    | name_union '|' name
;

catch:
    T_CATCH '(' name_union optional_plain_variable ')' '{' inner_statement_list '}'
        { $$ = Nodes\CatchNode[$1, $2, $3, $4, $5, Statement\BlockNode[$6, $7, $8]]; }
;

optional_finally:
      /* empty */
    | T_FINALLY '{' inner_statement_list '}'                { $$ = Nodes\FinallyNode[$1, Statement\BlockNode[$2, $3, $4]]; }
;

variables_list:
      non_empty_variables_list optional_comma
;

non_empty_variables_list:
      variable
    | non_empty_variables_list ',' variable
;

optional_ref:
      /* empty */
    | ampersand
;

optional_arg_ref:
      /* empty */
    | T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
;

optional_ellipsis:
      /* empty */
    | T_ELLIPSIS
;

block_or_error:
      '{' inner_statement_list '}'                          { $$ = Statement\BlockNode[$1, $2, $3]; }
;

fn_identifier:
      identifier_not_reserved
    | T_READONLY                                            { $$ = Nodes\IdentifierNode[$1]; }
    | T_EXIT                                                { $$ = Nodes\IdentifierNode[$1]; }
    | T_CLONE                                               { $$ = Nodes\IdentifierNode[$1]; }
;

function_declaration_statement:
      T_FUNCTION optional_ref fn_identifier '(' parameter_list ')' optional_return_type block_or_error
          { $$ = Statement\FunctionNode[init(), $1, $2, $3, $4, $5, $6, $7[0] ?? null, $7[1] ?? null, $8]; }
    | attributes T_FUNCTION optional_ref fn_identifier '(' parameter_list ')' optional_return_type block_or_error
          { $$ = Statement\FunctionNode[$1, $2, $3, $4, $5, $6, $7, $8[0] ?? null, $8[1] ?? null, $9]; }
;

class_declaration_statement:
      class_entry_type identifier_not_reserved extends_from implements_list '{' class_statement_list '}'
          { $$ = Statement\ClassNode[init(), $1[0], $1[1], $2, $3[0] ?? null, $3[1] ?? null, $4[0] ?? null, $4[1] ?? null, $5, $6, $7]; }
    | attributes class_entry_type identifier_not_reserved extends_from implements_list '{' class_statement_list '}'
          { $$ = Statement\ClassNode[$1, $2[0], $2[1], $3, $4[0] ?? null, $4[1] ?? null, $5[0] ?? null, $5[1] ?? null, $6, $7, $8]; }
    | optional_attributes T_INTERFACE identifier_not_reserved interface_extends_list '{' class_statement_list '}'
          { $$ = Statement\InterfaceNode[$1, $2, $3, $4[0] ?? null, $4[1] ?? null, $5, $6, $7]; }
    | optional_attributes T_TRAIT identifier_not_reserved '{' class_statement_list '}'
          { $$ = Statement\TraitNode[$1, $2, $3, $4, $5, $6]; }
    | optional_attributes T_ENUM identifier_not_reserved enum_scalar_type implements_list '{' class_statement_list '}'
          { $$ = Statement\EnumNode[$1, $2, $3, $4[0] ?? null, $4[1] ?? null, $5[0] ?? null, $5[1] ?? null, $6, $7, $8]; }
;

enum_scalar_type:
      /* empty */
    | ':' type                                              { $$ = [$1, $2]; }

enum_case_expr:
      /* empty */
    | '=' expr                                              { $$ = [$1, $2]; }
;

class_entry_type:
      T_CLASS                                               { $$ = [modifiers(), $1]; }
    | class_modifiers T_CLASS                               { $$ = [$1, $2]; }
;

class_modifiers:
      class_modifier
    | class_modifiers class_modifier
;

class_modifier:
      T_ABSTRACT
    | T_FINAL
    | T_READONLY
;

extends_from:
      /* empty */
    | T_EXTENDS class_name                                  { $$ = [$1, $2]; }
;

interface_extends_list:
      /* empty */
    | T_EXTENDS class_name_list                             { $$ = [$1, $2]; }
;

implements_list:
      /* empty */
    | T_IMPLEMENTS class_name_list                          { $$ = [$1, $2]; }
;

class_name_list:
      non_empty_class_name_list no_comma
;

non_empty_class_name_list:
      class_name
    | non_empty_class_name_list ',' class_name
;

for_statement:
      blocklike_statement                                   { $$ = [$1, null, null, null, null]; }
    | ':' inner_statement_list T_ENDFOR ';'                 { $$ = [null, $1, $2, $3, $4]; }
;

foreach_statement:
      blocklike_statement                                   { $$ = [$1, null, null, null, null]; }
    | ':' inner_statement_list T_ENDFOREACH ';'             { $$ = [null, $1, $2, $3, $4]; }
;

declare_statement:
      non_empty_statement                                   { $$ = [$1, null, null, null, null]; }
    | ';'                                                   { $$ = [null, null, null, null, $1]; }
    | ':' inner_statement_list T_ENDDECLARE ';'             { $$ = [null, $1, $2, $3, $4]; }
;

declare_list:
      non_empty_declare_list no_comma
;

non_empty_declare_list:
      declare_list_element
    | non_empty_declare_list ',' declare_list_element
;

declare_list_element:
      identifier_not_reserved '=' expr                      { $$ = Nodes\DeclareItemNode[$1, $2, $3]; }
;

switch_case_list:
      '{' case_list '}'                                     { $$ = [$1, null, null, $2, $3, null, null]; }
    | '{' ';' case_list '}'                                 { $$ = [$1, null, $2, $3, $4, null, null]; }
    | ':' case_list T_ENDSWITCH ';'                         { $$ = [null, $1, null, $2, null, $3, $4]; }
    | ':' ';' case_list T_ENDSWITCH ';'                     { $$ = [null, $1, $2, $3, null, $4, $5]; }
;

case_list:
      /* empty */
    | case_list case
;

case:
      T_CASE expr case_separator inner_statement_list_ex    { $$ = Nodes\CaseNode[$1, $2, $3, $4]; }
    | T_DEFAULT case_separator inner_statement_list_ex      { $$ = Nodes\CaseNode[$1, null, $2, $3]; }
;

case_separator:
      ':'
    | ';'
;

match:
      T_MATCH '(' expr ')' '{' match_arm_list '}'           { $$ = Expression\MatchNode[$1, $2, $3, $4, $5, $6, $7]; }
;

match_arm_list:
      /* empty */
    | non_empty_match_arm_list optional_comma
;

non_empty_match_arm_list:
      match_arm
    | non_empty_match_arm_list ',' match_arm
;

match_arm:
      expr_list_allow_comma T_DOUBLE_ARROW expr             { $$ = Nodes\MatchArmNode[$1, null, null, $2, $3]; }
    | T_DEFAULT optional_comma T_DOUBLE_ARROW expr          { $$ = Nodes\MatchArmNode[null, $1, $2, $3, $4]; }
;

while_statement:
      blocklike_statement                                   { $$ = [$1, null, null, null, null]; }
    | ':' inner_statement_list T_ENDWHILE ';'               { $$ = [null, $1, $2, $3, $4]; }
;

elseif_list:
      /* empty */
    | elseif_list elseif
;

elseif:
      T_ELSEIF '(' expr ')' blocklike_statement             { $$ = Nodes\ElseIfNode[$1, $2, $3, $4, $5, null, null]; }
;

new_elseif_list:
      /* empty */
    | new_elseif_list new_elseif
;

new_elseif:
     T_ELSEIF '(' expr ')' ':' inner_statement_list         { $$ = Nodes\ElseIfNode[$1, $2, $3, $4, null, $5, $6]; }
;

else_single:
      /* empty */
    | T_ELSE blocklike_statement                            { $$ = Nodes\ElseNode[$1, $2, null, null]; }
;

new_else_single:
      /* empty */
    | T_ELSE ':' inner_statement_list                       { $$ = Nodes\ElseNode[$1, null, $2, $3]; }
;

foreach_variable:
      variable                                              { $$ = [null, $1]; }
    | ampersand variable                                    { $$ = [$1, $2]; }
    | list_expr                                             { $$ = [null, $1]; }
    | array_short_syntax                                    { $$ = [null, $1]; }
;

parameter_list:
      non_empty_parameter_list optional_comma
    | /* empty */
;

non_empty_parameter_list:
      parameter
    | non_empty_parameter_list ',' parameter
;

optional_property_modifiers:
      /* empty */
    | optional_property_modifiers property_modifier
;

property_modifier:
      T_PUBLIC
    | T_PROTECTED
    | T_PRIVATE
    | T_PUBLIC_SET
    | T_PROTECTED_SET
    | T_PRIVATE_SET
    | T_READONLY
    | T_FINAL
;

parameter:
      optional_attributes optional_property_modifiers optional_type_without_static
      optional_arg_ref optional_ellipsis plain_variable optional_property_hook_list
          { $$ = Nodes\ParameterNode[$1, $2, $3, $4, $5, $6, null, null, $7[0] ?? null, $7[1] ?? null, $7[2] ?? null]; }
    | optional_attributes optional_property_modifiers optional_type_without_static
      optional_arg_ref optional_ellipsis plain_variable '=' expr optional_property_hook_list
          { $$ = Nodes\ParameterNode[$1, $2, $3, $4, $5, $6, $7, $8, $9[0] ?? null, $9[1] ?? null, $9[2] ?? null]; }
;

type_expr:
      type
    | '?' type                                              { $$ = Type\NullableTypeNode[$1, $2]; }
    | union_type                                            { $$ = Type\UnionTypeNode[$1]; }
    | intersection_type
;

type:
      type_without_static
    | T_STATIC                                              { $$ = Type\NamedTypeNode[Nodes\NameNode[$1]]; }
;

type_without_static:
      name                                                  { $$ = Type\NamedTypeNode[$1]; }
    | T_ARRAY                                               { $$ = Type\NamedTypeNode[Nodes\NameNode[$1]]; }
    | T_CALLABLE                                            { $$ = Type\NamedTypeNode[Nodes\NameNode[$1]]; }
;

union_type_element:
      type
    | '(' intersection_type ')'                             { $2->openParen = $1; $2->closeParen = $3; $$ = $2; }
;

union_type:
      union_type_element '|' union_type_element             { $$ = separated($1); push($$, $2, $3); }
    | union_type '|' union_type_element                     { push($1, $2, $3); }
;

union_type_without_static_element:
                type_without_static
        |        '(' intersection_type_without_static ')'   { $2->openParen = $1; $2->closeParen = $3; $$ = $2; }
;

union_type_without_static:
      union_type_without_static_element '|' union_type_without_static_element   { $$ = separated($1); push($$, $2, $3); }
    | union_type_without_static '|' union_type_without_static_element           { push($1, $2, $3); }
;

intersection_type_list:
      type T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG type   { $$ = separated($1); push($$, $2, $3); }
    | intersection_type_list T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG type
          { push($1, $2, $3); }
;

intersection_type:
      intersection_type_list                                { $$ = Type\IntersectionTypeNode[null, $1, null]; }
;

intersection_type_without_static_list:
      type_without_static T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG type_without_static
          { $$ = separated($1); push($$, $2, $3); }
    | intersection_type_without_static_list T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG type_without_static
          { push($1, $2, $3); }
;

intersection_type_without_static:
      intersection_type_without_static_list                 { $$ = Type\IntersectionTypeNode[null, $1, null]; }
;

type_expr_without_static:
      type_without_static
    | '?' type_without_static                               { $$ = Type\NullableTypeNode[$1, $2]; }
    | union_type_without_static                             { $$ = Type\UnionTypeNode[$1]; }
    | intersection_type_without_static
;

optional_type_without_static:
      /* empty */
    | type_expr_without_static
;

optional_return_type:
      /* empty */
    | ':' type_expr                                         { $$ = [$1, $2]; }
;

argument_list:
      '(' ')'                                               { $$ = Nodes\ArgumentListNode[$1, separated(), $2]; }
    | '(' non_empty_argument_list optional_comma ')'        { trailing($2, $3); $$ = Nodes\ArgumentListNode[$1, $2, $4]; }
    | '(' variadic_placeholder ')'                          { $$ = Nodes\ArgumentListNode[$1, separated($2), $3]; }
;

clone_argument_list:
      '(' ')'                                              { $$ = Nodes\ArgumentListNode[$1, separated(), $2]; }
    | '(' non_empty_clone_argument_list optional_comma ')' { trailing($2, $3); $$ = Nodes\ArgumentListNode[$1, $2, $4]; }
    | '(' expr ',' ')'                                     { $list = separated(Nodes\ArgumentNode[null, null, null, null, $2]); trailing($list, $3); $$ = Nodes\ArgumentListNode[$1, $list, $4]; }
    | '(' variadic_placeholder ')'                         { $$ = Nodes\ArgumentListNode[$1, separated($2), $3]; }
;

non_empty_clone_argument_list:
		expr ',' argument                                   { $$ = separated(Nodes\ArgumentNode[null, null, null, null, $1]); push($$, $2, $3); }
	|	argument_no_expr                                    { $$ = separated($1); }
	|	non_empty_clone_argument_list ',' argument          { push($1, $2, $3); }
;

variadic_placeholder:
      T_ELLIPSIS                                            { $$ = Nodes\VariadicPlaceholderNode[$1]; }
;

non_empty_argument_list:
      argument
    | non_empty_argument_list ',' argument
;

argument_no_expr:
      ampersand variable                                    { $$ = Nodes\ArgumentNode[null, null, $1, null, $2]; }
    | T_ELLIPSIS expr                                       { $$ = Nodes\ArgumentNode[null, null, null, $1, $2]; }
    | identifier_maybe_reserved ':' expr                    { $$ = Nodes\ArgumentNode[$1, $2, null, null, $3]; }
;

argument:
      expr                                                  { $$ = Nodes\ArgumentNode[null, null, null, null, $1]; }
    | argument_no_expr
;

global_var_list:
      non_empty_global_var_list no_comma
;

non_empty_global_var_list:
      non_empty_global_var_list ',' global_var
    | global_var
;

global_var:
      simple_variable
;

static_var_list:
      non_empty_static_var_list no_comma
;

non_empty_static_var_list:
      non_empty_static_var_list ',' static_var
    | static_var
;

static_var:
      plain_variable                                        { $$ = Nodes\StaticVariableNode[$1, null, null]; }
    | plain_variable '=' expr                               { $$ = Nodes\StaticVariableNode[$1, $2, $3]; }
;

class_statement_list_ex:
      class_statement_list_ex class_statement
    | /* empty */
;

class_statement_list:
      class_statement_list_ex
;

class_statement:
      optional_attributes variable_modifiers optional_type_without_static property_declaration_list semi
          { $$ = Member\PropertyNode[$1, $2, $3, $4, $5, null, null, null]; }
    | optional_attributes variable_modifiers optional_type_without_static property_declaration_list '{' property_hook_list '}'
          { $$ = Member\PropertyNode[$1, $2, $3, $4, null, $5, $6, $7]; }
    | optional_attributes method_modifiers T_CONST class_const_list semi
          { $$ = Member\ClassConstNode[$1, $2, $3, null, $4, $5]; }
    | optional_attributes method_modifiers T_CONST type_expr class_const_list semi
          { $$ = Member\ClassConstNode[$1, $2, $3, $4, $5, $6]; }
    | optional_attributes method_modifiers T_FUNCTION optional_ref identifier_maybe_reserved '(' parameter_list ')'
      optional_return_type method_body
          { $$ = Member\MethodNode[$1, $2, $3, $4, $5, $6, $7, $8, $9[0] ?? null, $9[1] ?? null, ...$10]; }
    | T_USE class_name_list trait_adaptations               { $$ = Member\TraitUseNode[$1, $2, ...$3]; }
    | optional_attributes T_CASE identifier_maybe_reserved enum_case_expr semi
         { $$ = Member\EnumCaseNode[$1, $2, $3, $4[0] ?? null, $4[1] ?? null, $5]; }
;

trait_adaptations:
      ';'                                                   { $$ = [$1, null, null, null]; }
    | '{' trait_adaptation_list '}'                         { $$ = [null, $1, $2, $3]; }
;

trait_adaptation_list:
      /* empty */
    | trait_adaptation_list trait_adaptation
;

trait_adaptation:
      trait_method_reference_fully_qualified T_INSTEADOF class_name_list ';'
          { $$ = Member\TraitPrecedenceNode[$1[0], $1[1], $1[2], $2, $3, $4]; }
    | trait_method_reference T_AS member_modifier identifier_maybe_reserved ';'
          { $$ = Member\TraitAliasNode[$1[0], $1[1], $1[2], $2, $3, $4, $5]; }
    | trait_method_reference T_AS member_modifier ';'
          { $$ = Member\TraitAliasNode[$1[0], $1[1], $1[2], $2, $3, null, $4]; }
    | trait_method_reference T_AS identifier_not_reserved ';'
          { $$ = Member\TraitAliasNode[$1[0], $1[1], $1[2], $2, null, $3, $4]; }
    | trait_method_reference T_AS reserved_non_modifiers_identifier ';'
          { $$ = Member\TraitAliasNode[$1[0], $1[1], $1[2], $2, null, $3, $4]; }
;

trait_method_reference_fully_qualified:
      name T_PAAMAYIM_NEKUDOTAYIM identifier_maybe_reserved { $$ = [$1, $2, $3]; }
;
trait_method_reference:
      trait_method_reference_fully_qualified
    | identifier_maybe_reserved                             { $$ = [null, null, $1]; }
;

method_body:
      ';' /* abstract method */                             { $$ = [null, $1]; }
    | block_or_error                                        { $$ = [$1, null]; }
;

variable_modifiers:
      non_empty_member_modifiers
    | T_VAR
;

method_modifiers:
      /* empty */
    | non_empty_member_modifiers
;

non_empty_member_modifiers:
      member_modifier
    | non_empty_member_modifiers member_modifier
;

member_modifier:
      T_PUBLIC
    | T_PROTECTED
    | T_PRIVATE
    | T_PUBLIC_SET
    | T_PROTECTED_SET
    | T_PRIVATE_SET
    | T_STATIC
    | T_ABSTRACT
    | T_FINAL
    | T_READONLY
;

property_declaration_list:
      non_empty_property_declaration_list no_comma
;

non_empty_property_declaration_list:
      property_declaration
    | non_empty_property_declaration_list ',' property_declaration
;

property_decl_name:
      T_VARIABLE
;

property_declaration:
      property_decl_name                                    { $$ = Member\PropertyItemNode[$1, null, null]; }
    | property_decl_name '=' expr                           { $$ = Member\PropertyItemNode[$1, $2, $3]; }
;

property_hook_list:
      /* empty */
    | property_hook_list property_hook
;

optional_property_hook_list:
      /* empty */
    | '{' property_hook_list '}'                            { $$ = [$1, $2, $3]; }
;

property_hook:
      optional_attributes property_hook_modifiers optional_ref identifier_not_reserved property_hook_body
          { $$ = Member\PropertyHookNode[$1, $2, $3, $4, null, null, null, ...$5]; }
    | optional_attributes property_hook_modifiers optional_ref identifier_not_reserved '(' parameter_list ')' property_hook_body
          { $$ = Member\PropertyHookNode[$1, $2, $3, $4, $5, $6, $7, ...$8]; }
;

property_hook_body:
      ';'                                                   { $$ = [null, null, null, $1]; }
    | '{' inner_statement_list '}'                          { $$ = [Statement\BlockNode[$1, $2, $3], null, null, null]; }
    | T_DOUBLE_ARROW expr ';'                               { $$ = [null, $1, $2, $3]; }
;

property_hook_modifiers:
      /* empty */
    | property_hook_modifiers member_modifier
;

expr_list_forbid_comma:
      non_empty_expr_list no_comma
;

expr_list_allow_comma:
      non_empty_expr_list optional_comma
;

non_empty_expr_list:
      non_empty_expr_list ',' expr
    | expr
;

for_expr:
      /* empty */
    | expr_list_forbid_comma
;

expr:
      variable
    | list_expr '=' expr                                    { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | array_short_syntax '=' expr                           { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable '=' expr                                     { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable '=' ampersand variable                       { $$ = Expression\AssignmentByReferenceNode[$1, $2, $3, $4]; }
    | variable '=' ampersand new_expr                       { $$ = Expression\AssignmentByReferenceNode[$1, $2, $3, $4]; }
    | new_expr
    | match
    | T_CLONE clone_argument_list                           { $$ = Expression\FunctionCallNode[Nodes\NameNode[$1], $2]; }
    | T_CLONE expr                                          { $$ = Expression\CloneNode[$1, $2]; }
    | variable T_PLUS_EQUAL expr                            { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_MINUS_EQUAL expr                           { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_MUL_EQUAL expr                             { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_DIV_EQUAL expr                             { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_CONCAT_EQUAL expr                          { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_MOD_EQUAL expr                             { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_AND_EQUAL expr                             { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_OR_EQUAL expr                              { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_XOR_EQUAL expr                             { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_SL_EQUAL expr                              { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_SR_EQUAL expr                              { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_POW_EQUAL expr                             { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_COALESCE_EQUAL expr                        { $$ = Expression\AssignmentNode[$1, $2, $3]; }
    | variable T_INC                                        { $$ = Expression\PostOpNode[$1, $2]; }
    | T_INC variable                                        { $$ = Expression\UnaryOpNode[$1, $2]; }
    | variable T_DEC                                        { $$ = Expression\PostOpNode[$1, $2]; }
    | T_DEC variable                                        { $$ = Expression\UnaryOpNode[$1, $2]; }
    | expr T_BOOLEAN_OR expr                                { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_BOOLEAN_AND expr                               { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_LOGICAL_OR expr                                { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_LOGICAL_AND expr                               { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_LOGICAL_XOR expr                               { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '|' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG expr   { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG expr       { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '^' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '.' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '+' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '-' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '*' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '/' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '%' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_SL expr                                        { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_SR expr                                        { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_POW expr                                       { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | '+' expr %prec T_INC                                  { $$ = Expression\UnaryOpNode[$1, $2]; }
    | '-' expr %prec T_INC                                  { $$ = Expression\UnaryOpNode[$1, $2]; }
    | '!' expr                                              { $$ = Expression\UnaryOpNode[$1, $2]; }
    | '~' expr                                              { $$ = Expression\UnaryOpNode[$1, $2]; }
    | expr T_IS_IDENTICAL expr                              { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_IS_NOT_IDENTICAL expr                          { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_IS_EQUAL expr                                  { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_IS_NOT_EQUAL expr                              { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_SPACESHIP expr                                 { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '<' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_IS_SMALLER_OR_EQUAL expr                       { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr '>' expr                                         { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_IS_GREATER_OR_EQUAL expr                       { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_PIPE expr                                      { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | expr T_INSTANCEOF class_name_reference                { $$ = Expression\InstanceofNode[$1, $2, $3]; }
    | '(' expr ')'                                          { $$ = Expression\ParenthesizedNode[$1, $2, $3]; }
    | expr '?' expr ':' expr                                { $$ = Expression\TernaryNode[$1, $2, $3, $4, $5]; }
    | expr '?' ':' expr                                     { $$ = Expression\TernaryNode[$1, $2, null, $3, $4]; }
    | expr T_COALESCE expr                                  { $$ = Expression\BinaryOpNode[$1, $2, $3]; }
    | T_ISSET '(' expr_list_allow_comma ')'                 { $$ = Expression\IssetNode[$1, $2, $3, $4]; }
    | T_EMPTY '(' expr ')'                                  { $$ = Expression\EmptyNode[$1, $2, $3, $4]; }
    | T_INCLUDE expr                                        { $$ = Expression\IncludeNode[$1, $2]; }
    | T_INCLUDE_ONCE expr                                   { $$ = Expression\IncludeNode[$1, $2]; }
    | T_EVAL '(' expr ')'                                   { $$ = Expression\EvalNode[$1, $2, $3, $4]; }
    | T_REQUIRE expr                                        { $$ = Expression\IncludeNode[$1, $2]; }
    | T_REQUIRE_ONCE expr                                   { $$ = Expression\IncludeNode[$1, $2]; }
    | T_INT_CAST expr                                       { $$ = Expression\CastNode[$1, $2]; }
    | T_DOUBLE_CAST expr                                    { $$ = Expression\CastNode[$1, $2]; }
    | T_STRING_CAST expr                                    { $$ = Expression\CastNode[$1, $2]; }
    | T_ARRAY_CAST expr                                     { $$ = Expression\CastNode[$1, $2]; }
    | T_OBJECT_CAST expr                                    { $$ = Expression\CastNode[$1, $2]; }
    | T_BOOL_CAST expr                                      { $$ = Expression\CastNode[$1, $2]; }
    | T_UNSET_CAST expr                                     { $$ = Expression\CastNode[$1, $2]; }
    | T_VOID_CAST expr                                      { $$ = Expression\CastNode[$1, $2]; }
    | T_EXIT ctor_arguments                                 { $$ = Expression\ExitNode[$1, $2]; }
    | '@' expr                                              { $$ = Expression\UnaryOpNode[$1, $2]; }
    | scalar
    | '`' backticks_expr '`'                                { $$ = Expression\ShellExecNode[$1, $2, $3]; }
    | T_PRINT expr                                          { $$ = Expression\PrintNode[$1, $2]; }
    | T_YIELD                                               { $$ = Expression\YieldNode[$1, null, null, null]; }
    | T_YIELD expr                                          { $$ = Expression\YieldNode[$1, null, null, $2]; }
    | T_YIELD expr T_DOUBLE_ARROW expr                      { $$ = Expression\YieldNode[$1, $2, $3, $4]; }
    | T_YIELD_FROM expr                                     { $$ = Expression\YieldFromNode[$1, $2]; }
    | T_THROW expr                                          { $$ = Expression\ThrowNode[$1, $2]; }

    | T_FN optional_ref '(' parameter_list ')' optional_return_type T_DOUBLE_ARROW expr %prec T_THROW
          { $$ = Expression\ArrowFunctionNode[init(), null, $1, $2, $3, $4, $5, $6[0] ?? null, $6[1] ?? null, $7, $8]; }
    | T_STATIC T_FN optional_ref '(' parameter_list ')' optional_return_type T_DOUBLE_ARROW expr %prec T_THROW
          { $$ = Expression\ArrowFunctionNode[init(), $1, $2, $3, $4, $5, $6, $7[0] ?? null, $7[1] ?? null, $8, $9]; }
    | T_FUNCTION optional_ref '(' parameter_list ')' lexical_vars optional_return_type block_or_error
          { $$ = Expression\ClosureNode[init(), null, $1, $2, $3, $4, $5, $6, $7[0] ?? null, $7[1] ?? null, $8]; }
    | T_STATIC T_FUNCTION optional_ref '(' parameter_list ')' lexical_vars optional_return_type       block_or_error
          { $$ = Expression\ClosureNode[init(), $1, $2, $3, $4, $5, $6, $7, $8[0] ?? null, $8[1] ?? null, $9]; }

    | attributes T_FN optional_ref '(' parameter_list ')' optional_return_type T_DOUBLE_ARROW expr %prec T_THROW
          { $$ = Expression\ArrowFunctionNode[$1, null, $2, $3, $4, $5, $6, $7[0] ?? null, $7[1] ?? null, $8, $9]; }
    | attributes T_STATIC T_FN optional_ref '(' parameter_list ')' optional_return_type T_DOUBLE_ARROW expr %prec T_THROW
          { $$ = Expression\ArrowFunctionNode[$1, $2, $3, $4, $5, $6, $7, $8[0] ?? null, $8[1] ?? null, $9, $10]; }
    | attributes T_FUNCTION optional_ref '(' parameter_list ')' lexical_vars optional_return_type block_or_error
          { $$ = Expression\ClosureNode[$1, null, $2, $3, $4, $5, $6, $7, $8[0] ?? null, $8[1] ?? null, $9]; }
    | attributes T_STATIC T_FUNCTION optional_ref '(' parameter_list ')' lexical_vars optional_return_type       block_or_error
          { $$ = Expression\ClosureNode[$1, $2, $3, $4, $5, $6, $7, $8, $9[0] ?? null, $9[1] ?? null, $10]; }
;

anonymous_class:
      optional_attributes class_entry_type ctor_arguments extends_from implements_list '{' class_statement_list '}'
          { $$ = Nodes\AnonymousClassNode[$1, $2[0], $2[1], $3, $4[0] ?? null, $4[1] ?? null, $5[0] ?? null, $5[1] ?? null, $6, $7, $8]; }
;

new_dereferenceable:
      T_NEW class_name_reference argument_list              { $$ = Expression\NewNode[$1, $2, $3]; }
    | T_NEW anonymous_class                                 { $$ = Expression\NewNode[$1, $2, null]; }
;

new_non_dereferenceable:
      T_NEW class_name_reference                            { $$ = Expression\NewNode[$1, $2, null]; }
;

new_expr:
      new_dereferenceable
    | new_non_dereferenceable
;

lexical_vars:
      /* empty */
    | T_USE '(' lexical_var_list ')'                        { $$ = Nodes\ClosureUsesNode[$1, $2, $3, $4]; }
;

lexical_var_list:
      non_empty_lexical_var_list optional_comma
;

non_empty_lexical_var_list:
      lexical_var
    | non_empty_lexical_var_list ',' lexical_var
;

lexical_var:
      optional_ref plain_variable                           { $$ = Nodes\ClosureUseNode[$1, $2]; }
;

name_readonly:
      T_READONLY
;

function_call:
      name argument_list                                    { $$ = Expression\FunctionCallNode[$1, $2]; }
    | name_readonly argument_list                           { $$ = Expression\FunctionCallNode[$1, $2]; }
    | callable_expr argument_list                           { $$ = Expression\FunctionCallNode[$1, $2]; }
    | class_name_or_var T_PAAMAYIM_NEKUDOTAYIM member_name argument_list
          { $$ = Expression\StaticMethodCallNode[$1, $2, $3[0], $3[1], $3[2], $4]; }
;

class_name:
      T_STATIC
    | name
;

name:
      T_STRING
    | T_NAME_QUALIFIED
    | T_NAME_FULLY_QUALIFIED
    | T_NAME_RELATIVE
;

class_name_reference:
      class_name
    | new_variable
    | '(' expr ')'                                          { $$ = Expression\ParenthesizedNode[$1, $2, $3]; }
;

class_name_or_var:
      class_name
    | fully_dereferenceable
;

backticks_expr:
      /* empty */                                           { $$ = init(); }
    | encaps_string_part                                    { $$ = init($1); }
    | encaps_list
;

ctor_arguments:
      /* empty */
    | argument_list
;

constant:
      name
    | T_LINE
    | T_FILE
    | T_DIR
    | T_CLASS_C
    | T_TRAIT_C
    | T_METHOD_C
    | T_FUNC_C
    | T_NS_C
    | T_PROPERTY_C
;

class_constant:
      class_name_or_var T_PAAMAYIM_NEKUDOTAYIM identifier_maybe_reserved
          { $$ = Expression\ClassConstantFetchNode[$1, $2, null, $3, null]; }
    | class_name_or_var T_PAAMAYIM_NEKUDOTAYIM '{' expr '}'
          { $$ = Expression\ClassConstantFetchNode[$1, $2, $3, $4, $5]; }
;

array_short_syntax:
      '[' array_pair_list ']'                               { $$ = Expression\ArrayNode[null, $1, $2, $3]; }
;

dereferenceable_scalar:
      T_ARRAY '(' array_pair_list ')'                       { $$ = Expression\ArrayNode[$1, $2, $3, $4]; }
    | array_short_syntax
    | T_CONSTANT_ENCAPSED_STRING
    | '"' encaps_list '"'
;

scalar:
      T_LNUMBER
    | T_DNUMBER
    | dereferenceable_scalar
    | constant
    | class_constant
    | T_START_HEREDOC T_ENCAPSED_AND_WHITESPACE T_END_HEREDOC
          { $$ = Scalar\HeredocNode[$1, init(Scalar\InterpolatedStringPartNode[$2]), $3]; }
    | T_START_HEREDOC T_END_HEREDOC                         { $$ = Scalar\HeredocNode[$1, init(), $2]; }
    | T_START_HEREDOC encaps_list T_END_HEREDOC             { $$ = Scalar\HeredocNode[$1, $2, $3]; }
;

optional_expr:
      /* empty */
    | expr
;

fully_dereferenceable:
      variable
    | '(' expr ')'                                          { $$ = Expression\ParenthesizedNode[$1, $2, $3]; }
    | dereferenceable_scalar
    | class_constant
    | new_dereferenceable
;

array_object_dereferenceable:
      fully_dereferenceable
    | constant
;

callable_expr:
      callable_variable
    | '(' expr ')'                                          { $$ = Expression\ParenthesizedNode[$1, $2, $3]; }
    | dereferenceable_scalar
    | new_dereferenceable
;

callable_variable:
      simple_variable
    | array_object_dereferenceable '[' optional_expr ']'     { $$ = Expression\ArrayAccessNode[$1, $2, $3, $4]; }
    | function_call
    | array_object_dereferenceable T_OBJECT_OPERATOR property_name argument_list
          { $$ = Expression\MethodCallNode[$1, $2, $3[0], $3[1], $3[2], $4]; }
    | array_object_dereferenceable T_NULLSAFE_OBJECT_OPERATOR property_name argument_list
          { $$ = Expression\MethodCallNode[$1, $2, $3[0], $3[1], $3[2], $4]; }
;

optional_plain_variable:
      /* empty */
    | plain_variable
;

variable:
      callable_variable
    | static_member
    | array_object_dereferenceable T_OBJECT_OPERATOR property_name
          { $$ = Expression\PropertyFetchNode[$1, $2, $3[0], $3[1], $3[2]]; }
    | array_object_dereferenceable T_NULLSAFE_OBJECT_OPERATOR property_name
          { $$ = Expression\PropertyFetchNode[$1, $2, $3[0], $3[1], $3[2]]; }
;

simple_variable:
      plain_variable
    | '$' '{' expr '}'                                      { $$ = Expression\VariableNode[$1, $2, $3, $4]; }
    | '$' simple_variable                                   { $$ = Expression\VariableNode[$1, null, $2, null]; }
;

static_member_prop_name:
      simple_variable
;

static_member:
      class_name_or_var T_PAAMAYIM_NEKUDOTAYIM static_member_prop_name
          { $$ = Expression\StaticPropertyFetchNode[$1, $2, $3]; }
;

new_variable:
      simple_variable
    | new_variable '[' optional_expr ']'                    { $$ = Expression\ArrayAccessNode[$1, $2, $3, $4]; }
    | new_variable T_OBJECT_OPERATOR property_name          { $$ = Expression\PropertyFetchNode[$1, $2, $3[0], $3[1], $3[2]]; }
    | new_variable T_NULLSAFE_OBJECT_OPERATOR property_name { $$ = Expression\PropertyFetchNode[$1, $2, $3[0], $3[1], $3[2]]; }
    | class_name T_PAAMAYIM_NEKUDOTAYIM static_member_prop_name
          { $$ = Expression\StaticPropertyFetchNode[$1, $2, $3]; }
    | new_variable T_PAAMAYIM_NEKUDOTAYIM static_member_prop_name
          { $$ = Expression\StaticPropertyFetchNode[$1, $2, $3]; }
;

member_name:
      identifier_maybe_reserved                             { $$ = [null, $1, null]; }
    | '{' expr '}'                                          { $$ = [$1, $2, $3]; }
    | simple_variable                                       { $$ = [null, $1, null]; }
;

property_name:
      identifier_not_reserved                               { $$ = [null, $1, null]; }
    | '{' expr '}'                                          { $$ = [$1, $2, $3]; }
    | simple_variable                                       { $$ = [null, $1, null]; }
;

list_expr:
      T_LIST '(' inner_array_pair_list ')'                  { $$ = Expression\ListNode[$1, $2, $this->finishArrayItems($3), $4]; }
;

array_pair_list:
      inner_array_pair_list
;

inner_array_pair_list:
      inner_array_pair_list ',' array_pair
    | array_pair
;

array_pair:
      expr
    | ampersand variable
    | list_expr
    | expr T_DOUBLE_ARROW expr
    | expr T_DOUBLE_ARROW ampersand variable
    | expr T_DOUBLE_ARROW list_expr
    | T_ELLIPSIS expr
    | /* empty */
;

encaps_list:
      encaps_list encaps_var                                { push($1, $2); }
    | encaps_list encaps_string_part                        { push($1, $2); }
    | encaps_var                                            { $$ = init($1); }
    | encaps_string_part encaps_var                         { $$ = init($1); push($$, $2); }
;

encaps_string_part:
      T_ENCAPSED_AND_WHITESPACE                             { $$ = Scalar\InterpolatedStringPartNode[$1]; }
;

encaps_str_varname:
      T_STRING_VARNAME                                      { $$ = Expression\VariableNode[null, null, $1, null]; }
;

encaps_var:
      plain_variable
    | plain_variable '[' encaps_var_offset ']'              { $$ = Expression\ArrayAccessNode[$1, $2, $3, $4]; }
    | plain_variable T_OBJECT_OPERATOR identifier_not_reserved
          { $$ = Expression\PropertyFetchNode[$1, $2, null, $3, null]; }
    | plain_variable T_NULLSAFE_OBJECT_OPERATOR identifier_not_reserved
          { $$ = Expression\PropertyFetchNode[$1, $2, null, $3, null]; }
    | T_DOLLAR_OPEN_CURLY_BRACES expr '}'                   { $$ = Scalar\InterpolationNode[$1, $2, $3]; }
    | T_DOLLAR_OPEN_CURLY_BRACES T_STRING_VARNAME '}'       { $$ = Scalar\InterpolationNode[$1, Expression\VariableNode[null, null, $2, null], $3]; }
    | T_DOLLAR_OPEN_CURLY_BRACES encaps_str_varname '[' expr ']' '}'
          { $$ = Scalar\InterpolationNode[$1, Expression\ArrayAccessNode[$2, $3, $4, $5], $6]; }
    | T_CURLY_OPEN variable '}'                             { $$ = Scalar\InterpolationNode[$1, $2, $3]; }
;

encaps_var_offset:
      T_STRING                                              { $$ = Scalar\StringNode[$1]; }
    | T_NUM_STRING                                          { $$ = $this->offsetNumber($1); }
    | '-' T_NUM_STRING                                      { $$ = Expression\UnaryOpNode[$1, Scalar\IntegerNode[$2]]; }
    | plain_variable
;

%%
