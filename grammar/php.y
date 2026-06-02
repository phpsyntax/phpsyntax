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
      class_name
    | class_name argument_list
;

attribute_group:
      attribute_decl
    | attribute_group ',' attribute_decl
;

attribute:
      T_ATTRIBUTE attribute_group optional_comma ']'
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
    | T_NAMESPACE '{' top_statement_list '}'
    | T_USE use_declarations semi
    | T_USE use_type use_declarations semi
    | group_use_declaration
    | T_CONST constant_declaration_list semi
    | attributes T_CONST constant_declaration_list semi
;

use_type:
      T_FUNCTION
    | T_CONST
;

group_use_declaration:
      T_USE use_type legacy_namespace_name T_NS_SEPARATOR '{' unprefixed_use_declarations '}' semi
    | T_USE legacy_namespace_name T_NS_SEPARATOR '{' inline_use_declarations '}' semi
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
      namespace_name
    | namespace_name T_AS identifier_not_reserved
;

use_declaration:
      legacy_namespace_name
    | legacy_namespace_name T_AS identifier_not_reserved
;

inline_use_declaration:
      unprefixed_use_declaration
    | use_type unprefixed_use_declaration
;

constant_declaration_list:
      non_empty_constant_declaration_list no_comma
;

non_empty_constant_declaration_list:
      non_empty_constant_declaration_list ',' constant_declaration
    | constant_declaration
;

constant_declaration:
    identifier_not_reserved '=' expr
;

class_const_list:
      non_empty_class_const_list no_comma
;

non_empty_class_const_list:
      non_empty_class_const_list ',' class_const
    | class_const
;

class_const:
      T_STRING '=' expr
    | semi_reserved '=' expr
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
      '{' inner_statement_list '}'
    | T_IF '(' expr ')' blocklike_statement elseif_list else_single
    | T_IF '(' expr ')' ':' inner_statement_list new_elseif_list new_else_single T_ENDIF ';'
    | T_WHILE '(' expr ')' while_statement
    | T_DO blocklike_statement T_WHILE '(' expr ')' ';'
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
    | T_FOREACH '(' expr T_AS variable T_DOUBLE_ARROW foreach_variable ')' foreach_statement
    | T_DECLARE '(' declare_list ')' declare_statement
    | T_TRY '{' inner_statement_list '}' catches optional_finally
    | T_GOTO identifier_not_reserved semi
    | identifier_not_reserved ':'
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
;

optional_finally:
      /* empty */
    | T_FINALLY '{' inner_statement_list '}'
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
      '{' inner_statement_list '}'
;

fn_identifier:
      identifier_not_reserved
    | T_READONLY
    | T_EXIT
    | T_CLONE
;

function_declaration_statement:
      T_FUNCTION optional_ref fn_identifier '(' parameter_list ')' optional_return_type block_or_error
    | attributes T_FUNCTION optional_ref fn_identifier '(' parameter_list ')' optional_return_type block_or_error
;

class_declaration_statement:
      class_entry_type identifier_not_reserved extends_from implements_list '{' class_statement_list '}'
    | attributes class_entry_type identifier_not_reserved extends_from implements_list '{' class_statement_list '}'
    | optional_attributes T_INTERFACE identifier_not_reserved interface_extends_list '{' class_statement_list '}'
    | optional_attributes T_TRAIT identifier_not_reserved '{' class_statement_list '}'
    | optional_attributes T_ENUM identifier_not_reserved enum_scalar_type implements_list '{' class_statement_list '}'
;

enum_scalar_type:
      /* empty */
    | ':' type

enum_case_expr:
      /* empty */
    | '=' expr
;

class_entry_type:
      T_CLASS
    | class_modifiers T_CLASS
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
    | T_EXTENDS class_name
;

interface_extends_list:
      /* empty */
    | T_EXTENDS class_name_list
;

implements_list:
      /* empty */
    | T_IMPLEMENTS class_name_list
;

class_name_list:
      non_empty_class_name_list no_comma
;

non_empty_class_name_list:
      class_name
    | non_empty_class_name_list ',' class_name
;

for_statement:
      blocklike_statement
    | ':' inner_statement_list T_ENDFOR ';'
;

foreach_statement:
      blocklike_statement
    | ':' inner_statement_list T_ENDFOREACH ';'
;

declare_statement:
      non_empty_statement
    | ';'
    | ':' inner_statement_list T_ENDDECLARE ';'
;

declare_list:
      non_empty_declare_list no_comma
;

non_empty_declare_list:
      declare_list_element
    | non_empty_declare_list ',' declare_list_element
;

declare_list_element:
      identifier_not_reserved '=' expr
;

switch_case_list:
      '{' case_list '}'
    | '{' ';' case_list '}'
    | ':' case_list T_ENDSWITCH ';'
    | ':' ';' case_list T_ENDSWITCH ';'
;

case_list:
      /* empty */
    | case_list case
;

case:
      T_CASE expr case_separator inner_statement_list_ex
    | T_DEFAULT case_separator inner_statement_list_ex
;

case_separator:
      ':'
    | ';'
;

match:
      T_MATCH '(' expr ')' '{' match_arm_list '}'
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
      expr_list_allow_comma T_DOUBLE_ARROW expr
    | T_DEFAULT optional_comma T_DOUBLE_ARROW expr
;

while_statement:
      blocklike_statement
    | ':' inner_statement_list T_ENDWHILE ';'
;

elseif_list:
      /* empty */
    | elseif_list elseif
;

elseif:
      T_ELSEIF '(' expr ')' blocklike_statement
;

new_elseif_list:
      /* empty */
    | new_elseif_list new_elseif
;

new_elseif:
     T_ELSEIF '(' expr ')' ':' inner_statement_list
;

else_single:
      /* empty */
    | T_ELSE blocklike_statement
;

new_else_single:
      /* empty */
    | T_ELSE ':' inner_statement_list
;

foreach_variable:
      variable
    | ampersand variable
    | list_expr
    | array_short_syntax
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
    | optional_attributes optional_property_modifiers optional_type_without_static
      optional_arg_ref optional_ellipsis plain_variable '=' expr optional_property_hook_list
;

type_expr:
      type
    | '?' type
    | union_type
    | intersection_type
;

type:
      type_without_static
    | T_STATIC
;

type_without_static:
      name
    | T_ARRAY
    | T_CALLABLE
;

union_type_element:
      type
    | '(' intersection_type ')'
;

union_type:
      union_type_element '|' union_type_element
    | union_type '|' union_type_element
;

union_type_without_static_element:
                type_without_static
        |        '(' intersection_type_without_static ')'
;

union_type_without_static:
      union_type_without_static_element '|' union_type_without_static_element
    | union_type_without_static '|' union_type_without_static_element
;

intersection_type_list:
      type T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG type
    | intersection_type_list T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG type
;

intersection_type:
      intersection_type_list
;

intersection_type_without_static_list:
      type_without_static T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG type_without_static
    | intersection_type_without_static_list T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG type_without_static
;

intersection_type_without_static:
      intersection_type_without_static_list
;

type_expr_without_static:
      type_without_static
    | '?' type_without_static
    | union_type_without_static
    | intersection_type_without_static
;

optional_type_without_static:
      /* empty */
    | type_expr_without_static
;

optional_return_type:
      /* empty */
    | ':' type_expr
;

argument_list:
      '(' ')'
    | '(' non_empty_argument_list optional_comma ')'
;

clone_argument_list:
      '(' ')'
    | '(' non_empty_clone_argument_list optional_comma ')'
    | '(' expr ',' ')'
;

non_empty_clone_argument_list:
		expr ',' argument
	|	argument_no_expr
	|	non_empty_clone_argument_list ',' argument
;

non_empty_argument_list:
      argument
    | non_empty_argument_list ',' argument
;

argument_no_expr:
      ampersand variable
    | T_ELLIPSIS expr
    | T_ELLIPSIS
    | '?'
    | identifier_maybe_reserved ':' expr
    | identifier_maybe_reserved ':' '?'
;

argument:
      expr
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
      plain_variable
    | plain_variable '=' expr
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
    | optional_attributes variable_modifiers optional_type_without_static property_declaration_list '{' property_hook_list '}'
    | optional_attributes method_modifiers T_CONST class_const_list semi
    | optional_attributes method_modifiers T_CONST type_expr class_const_list semi
    | optional_attributes method_modifiers T_FUNCTION optional_ref identifier_maybe_reserved '(' parameter_list ')'
      optional_return_type method_body
    | T_USE class_name_list trait_adaptations
    | optional_attributes T_CASE identifier_maybe_reserved enum_case_expr semi
;

trait_adaptations:
      ';'
    | '{' trait_adaptation_list '}'
;

trait_adaptation_list:
      /* empty */
    | trait_adaptation_list trait_adaptation
;

trait_adaptation:
      trait_method_reference_fully_qualified T_INSTEADOF class_name_list ';'
    | trait_method_reference T_AS member_modifier identifier_maybe_reserved ';'
    | trait_method_reference T_AS member_modifier ';'
    | trait_method_reference T_AS identifier_not_reserved ';'
    | trait_method_reference T_AS reserved_non_modifiers_identifier ';'
;

trait_method_reference_fully_qualified:
      name T_PAAMAYIM_NEKUDOTAYIM identifier_maybe_reserved
;
trait_method_reference:
      trait_method_reference_fully_qualified
    | identifier_maybe_reserved
;

method_body:
      ';' /* abstract method */
    | block_or_error
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
      property_decl_name
    | property_decl_name '=' expr
;

property_hook_list:
      /* empty */
    | property_hook_list property_hook
;

optional_property_hook_list:
      /* empty */
    | '{' property_hook_list '}'
;

property_hook:
      optional_attributes property_hook_modifiers optional_ref identifier_not_reserved property_hook_body
    | optional_attributes property_hook_modifiers optional_ref identifier_not_reserved '(' parameter_list ')' property_hook_body
;

property_hook_body:
      ';'
    | '{' inner_statement_list '}'
    | T_DOUBLE_ARROW expr ';'
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
    | list_expr '=' expr
    | array_short_syntax '=' expr
    | variable '=' expr
    | variable '=' ampersand variable
    | variable '=' ampersand new_expr
    | new_expr
    | match
    | T_CLONE clone_argument_list
    | T_CLONE expr
    | variable T_PLUS_EQUAL expr
    | variable T_MINUS_EQUAL expr
    | variable T_MUL_EQUAL expr
    | variable T_DIV_EQUAL expr
    | variable T_CONCAT_EQUAL expr
    | variable T_MOD_EQUAL expr
    | variable T_AND_EQUAL expr
    | variable T_OR_EQUAL expr
    | variable T_XOR_EQUAL expr
    | variable T_SL_EQUAL expr
    | variable T_SR_EQUAL expr
    | variable T_POW_EQUAL expr
    | variable T_COALESCE_EQUAL expr
    | variable T_INC
    | T_INC variable
    | variable T_DEC
    | T_DEC variable
    | expr T_BOOLEAN_OR expr
    | expr T_BOOLEAN_AND expr
    | expr T_LOGICAL_OR expr
    | expr T_LOGICAL_AND expr
    | expr T_LOGICAL_XOR expr
    | expr '|' expr
    | expr T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG expr
    | expr T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG expr
    | expr '^' expr
    | expr '.' expr
    | expr '+' expr
    | expr '-' expr
    | expr '*' expr
    | expr '/' expr
    | expr '%' expr
    | expr T_SL expr
    | expr T_SR expr
    | expr T_POW expr
    | '+' expr %prec T_INC
    | '-' expr %prec T_INC
    | '!' expr
    | '~' expr
    | expr T_IS_IDENTICAL expr
    | expr T_IS_NOT_IDENTICAL expr
    | expr T_IS_EQUAL expr
    | expr T_IS_NOT_EQUAL expr
    | expr T_SPACESHIP expr
    | expr '<' expr
    | expr T_IS_SMALLER_OR_EQUAL expr
    | expr '>' expr
    | expr T_IS_GREATER_OR_EQUAL expr
    | expr T_PIPE expr
    | expr T_INSTANCEOF class_name_reference
    | '(' expr ')'
    | expr '?' expr ':' expr
    | expr '?' ':' expr
    | expr T_COALESCE expr
    | T_ISSET '(' expr_list_allow_comma ')'
    | T_EMPTY '(' expr ')'
    | T_INCLUDE expr
    | T_INCLUDE_ONCE expr
    | T_EVAL '(' expr ')'
    | T_REQUIRE expr
    | T_REQUIRE_ONCE expr
    | T_INT_CAST expr
    | T_DOUBLE_CAST expr
    | T_STRING_CAST expr
    | T_ARRAY_CAST expr
    | T_OBJECT_CAST expr
    | T_BOOL_CAST expr
    | T_UNSET_CAST expr
    | T_VOID_CAST expr
    | T_EXIT ctor_arguments
    | '@' expr
    | scalar
    | '`' backticks_expr '`'
    | T_PRINT expr
    | T_YIELD
    | T_YIELD expr
    | T_YIELD expr T_DOUBLE_ARROW expr
    | T_YIELD_FROM expr
    | T_THROW expr
    | T_FN optional_ref '(' parameter_list ')' optional_return_type T_DOUBLE_ARROW expr %prec T_THROW
    | T_STATIC T_FN optional_ref '(' parameter_list ')' optional_return_type T_DOUBLE_ARROW expr %prec T_THROW
    | T_FUNCTION optional_ref '(' parameter_list ')' lexical_vars optional_return_type block_or_error
    | T_STATIC T_FUNCTION optional_ref '(' parameter_list ')' lexical_vars optional_return_type       block_or_error
    | attributes T_FN optional_ref '(' parameter_list ')' optional_return_type T_DOUBLE_ARROW expr %prec T_THROW
    | attributes T_STATIC T_FN optional_ref '(' parameter_list ')' optional_return_type T_DOUBLE_ARROW expr %prec T_THROW
    | attributes T_FUNCTION optional_ref '(' parameter_list ')' lexical_vars optional_return_type block_or_error
    | attributes T_STATIC T_FUNCTION optional_ref '(' parameter_list ')' lexical_vars optional_return_type       block_or_error
;

anonymous_class:
      optional_attributes class_entry_type ctor_arguments extends_from implements_list '{' class_statement_list '}'
;

new_dereferenceable:
      T_NEW class_name_reference argument_list
    | T_NEW anonymous_class
;

new_non_dereferenceable:
      T_NEW class_name_reference
;

new_expr:
      new_dereferenceable
    | new_non_dereferenceable
;

lexical_vars:
      /* empty */
    | T_USE '(' lexical_var_list ')'
;

lexical_var_list:
      non_empty_lexical_var_list optional_comma
;

non_empty_lexical_var_list:
      lexical_var
    | non_empty_lexical_var_list ',' lexical_var
;

lexical_var:
      optional_ref plain_variable
;

name_readonly:
      T_READONLY
;

function_call:
      name argument_list
    | name_readonly argument_list
    | callable_expr argument_list
    | class_name_or_var T_PAAMAYIM_NEKUDOTAYIM member_name argument_list
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
    | '(' expr ')'
;

class_name_or_var:
      class_name
    | fully_dereferenceable
;

backticks_expr:
      /* empty */
    | encaps_string_part
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
    | class_name_or_var T_PAAMAYIM_NEKUDOTAYIM '{' expr '}'
;

array_short_syntax:
      '[' array_pair_list ']'
;

dereferenceable_scalar:
      T_ARRAY '(' array_pair_list ')'
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
    | T_START_HEREDOC T_END_HEREDOC
    | T_START_HEREDOC encaps_list T_END_HEREDOC
;

optional_expr:
      /* empty */
    | expr
;

fully_dereferenceable:
      variable
    | '(' expr ')'
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
    | '(' expr ')'
    | dereferenceable_scalar
    | new_dereferenceable
;

callable_variable:
      simple_variable
    | array_object_dereferenceable '[' optional_expr ']'
    | function_call
    | array_object_dereferenceable T_OBJECT_OPERATOR property_name argument_list
    | array_object_dereferenceable T_NULLSAFE_OBJECT_OPERATOR property_name argument_list
;

optional_plain_variable:
      /* empty */
    | plain_variable
;

variable:
      callable_variable
    | static_member
    | array_object_dereferenceable T_OBJECT_OPERATOR property_name
    | array_object_dereferenceable T_NULLSAFE_OBJECT_OPERATOR property_name
;

simple_variable:
      plain_variable
    | '$' '{' expr '}'
    | '$' simple_variable
;

static_member_prop_name:
      simple_variable
;

static_member:
      class_name_or_var T_PAAMAYIM_NEKUDOTAYIM static_member_prop_name
;

new_variable:
      simple_variable
    | new_variable '[' optional_expr ']'
    | new_variable T_OBJECT_OPERATOR property_name
    | new_variable T_NULLSAFE_OBJECT_OPERATOR property_name
    | class_name T_PAAMAYIM_NEKUDOTAYIM static_member_prop_name
    | new_variable T_PAAMAYIM_NEKUDOTAYIM static_member_prop_name
;

member_name:
      identifier_maybe_reserved
    | '{' expr '}'
    | simple_variable
;

property_name:
      identifier_not_reserved
    | '{' expr '}'
    | simple_variable
;

list_expr:
      T_LIST '(' inner_array_pair_list ')'
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
      encaps_list encaps_var
    | encaps_list encaps_string_part
    | encaps_var
    | encaps_string_part encaps_var
;

encaps_string_part:
      T_ENCAPSED_AND_WHITESPACE
;

encaps_str_varname:
      T_STRING_VARNAME
;

encaps_var:
      plain_variable
    | plain_variable '[' encaps_var_offset ']'
    | plain_variable T_OBJECT_OPERATOR identifier_not_reserved
    | plain_variable T_NULLSAFE_OBJECT_OPERATOR identifier_not_reserved
    | T_DOLLAR_OPEN_CURLY_BRACES expr '}'
    | T_DOLLAR_OPEN_CURLY_BRACES T_STRING_VARNAME '}'
    | T_DOLLAR_OPEN_CURLY_BRACES encaps_str_varname '[' expr ']' '}'
    | T_CURLY_OPEN variable '}'
;

encaps_var_offset:
      T_STRING
    | T_NUM_STRING
    | '-' T_NUM_STRING
    | plain_variable
;

%%
