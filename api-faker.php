<?php

use JetBrains\PhpStorm\NoReturn;
use Random\RandomException;

const FAKER_SHOW_WARN = false;
$json_schema = file_get_contents( 'schema.json' );

/**
 * Print JSON response
 *
 * @param mixed $content The result content to print as JSON
 * @return void
 */
#[NoReturn] function json_response( mixed $content ) : void {
    header( 'Content-Type: application/json; charset=utf-8' );
    echo json_encode( $content, JSON_PRETTY_PRINT );
    exit;
}

/**
 * Translate types (handle arguments)
 *
 * @param array $route The route to respond
 * @param array $args Route arguments (can be empty)
 * @return array
 * @throws RandomException
 */
function handle_response( array $route, array $args=array() ) : array {
    $count = 0;
    $int_count = 0;
    array_walk_recursive( $route, function( &$current, $key ) use ( &$count, &$int_count, $args ) {
        @list( $type, $param ) = explode( ':', $current );
        if( ! isset( $param ) ) $param = null;
        switch( $type ) {
            case 'string':
                $length = 10;
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                switch( $param ) {
                    case 'lorem':
                        $current = 'Lorem ipsum commodi autem hic eum est blanditiis dolor it.';
                        break;
                    case @ctype_digit( $param ):
                        $length = intval( $param );
                    default:
                        $result = '';
                        for( $i = 0; $i <= $length; $i++ ) $result .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
                        $current = $result;
                        break;
                }
                break;
            case 'int':
                switch( $param ) {
                    case @ctype_digit( $param ):
                        $current = rand( intval( str_pad( '1', intval( $param ), '0' ) ), intval( str_pad( '9', intval( $param ), '9' ) ) );
                        break;
                    case 'c':
                        $current = $int_count;
                        $int_count++;
                        break;
                }
                break;
            case 'double':
                $current = mt_rand( 10000000, 99999999 ) / 1000000.0;
                break;
            case 'args':
                if( ! @ctype_digit( $param ) ) $param = 1;
                else $param = intval( $param ) != 0 ? intval( $param ) - 1 : 0;
                $current = $args[ $param ] ?? 'Undefined argument ' . ( $param + 1 );
                break;
            case 'gps':
                switch( $param ) {
                    case 'lat':
                        $current = mt_rand( -90000000, 90000000 ) / 1000000.0;
                        break;
                    case 'lon':
                        $current = mt_rand( -180000000, 180000000 ) / 1000000.0;
                        break;
                }
                break;
        }
        $count++;
    } );
    return $route;
}

/**
 * Handle route rules and global rules
 *
 * @param array $global Global rules (can be empty)
 * @param array $route_rules Route rules (can be empty)
 * @return array
 */
function route_rules( array $global=array(), array $route_rules=array() ) : array {
    $delay = false;
    $code = 200;
    if( ! isset( $route_rules[ 'type' ] ) ) $type = 'unit';
    else $type = $route_rules[ 'type' ];
    if( isset( $global[ 'delay' ] ) && ( @ctype_digit( $global[ 'delay' ] ) || is_int( $global[ 'delay' ] ) ) ) $delay = @intval( $global[ 'delay' ] ) ?? false;
    if( isset( $route_rules[ 'delay' ] ) && ( @ctype_digit( $route_rules[ 'delay' ] ) || is_int( $route_rules[ 'delay' ] ) ) ) $delay = @intval( $route_rules[ 'delay' ] ) ?? 0;
    if( isset( $global[ 'headers' ] ) && is_array( $global[ 'headers' ] ) ) foreach( $global[ 'headers' ] as $header ) @header( $header );
    if( isset( $route_rules[ 'headers' ] ) && is_array( $route_rules[ 'headers' ] ) ) foreach( $route_rules[ 'headers' ] as $header ) @header( $header );
    if( isset( $global[ 'code' ] ) && ( @ctype_digit( $global[ 'code' ] ) || is_int( $global[ 'code' ] ) ) ) $code = @intval( $global[ 'code' ] );
    if( isset( $route_rules[ 'code' ] ) && ( @ctype_digit( $route_rules[ 'code' ] ) || is_int( $route_rules[ 'code' ] ) ) ) $code = @intval( $route_rules[ 'code' ] );
    return array( $type, $delay, $code );
}

/**
 * Handle route rules with method (handle arguments)
 *
 * @param array $content The decoded schema content
 * @param string $route The route to handle (theoretically current route)
 * @param string $method Used method
 * @param array $args Route arguments (can be empty)
 * @return void
 */
function handle_route_rules( array $content, string $route, string $method, array $args=array() ) : void {
    if( empty( $content[ 'rules' ] ) ) if( FAKER_SHOW_WARN ) trigger_error( 'Rules not implemented', E_USER_WARNING );
    $rules = $content[ 'rules' ] ?? array();
    if( empty( $rules[ $route ][ $method ] ) ) if( FAKER_SHOW_WARN ) trigger_error( "No rules for route : $route ($method)", E_USER_WARNING );
    $global = array();
    if(
        ! empty( $rules[ 'global' ] ) &&
        ! empty( $rules[ 'global' ][ $method ] )
    ) $global = $rules[ 'global' ][ $method ];
    $route_rules = $rules[ $route ][ $method ] ?? array();
    list( $type, $delay, $response_code ) = route_rules( $global, $route_rules );
    http_response_code( $response_code ?? 200 );
    switch( $type ) {
        case 'array':
            if( ! isset( $route_rules[ 'count' ] ) ) $count = 5;
            else $count = $route_rules[ 'count' ];
            $result = array_fill( 0, $count, $content[ 'routes' ][ $route ][ $method ] );
            if( $delay !== false ) usleep( $delay * 1000 );
            json_response( handle_response( $result, $args ) );
            break;
        case 'unit':
        default:
            if( $delay !== false ) usleep( $delay * 1000 );
            json_response( handle_response( $content[ 'routes' ][ $route ][ $method ], $args ) );
            break;
    }
}

/**
 * Handle nested routes and rules
 *
 * @param array $content The decoded schema content
 * @param string $base_route The route where to start
 * @param string $scope The scope to search on
 * @return void
 */
function sanitize_nested( array &$content, string $base_route='/', string $scope='routes' ) : void {
    $methods = array( 'GET', 'POST' );
    if( ! isset( $content[ $scope ][ $base_route ] ) || ! is_array( $content[ $scope ][ $base_route ] ) ) return;
    $filtered = array_filter( $content[ $scope ][ $base_route ], fn( $x ) => ! in_array( $x, $methods ), ARRAY_FILTER_USE_KEY );
    foreach( $filtered as $new_route => $route_content ) {
        $content[ $scope ][ ( $base_route === '/' ? rtrim( $base_route, '/' ) : $base_route ) . ( strpos( $new_route, '/' ) !== 0 ? "/$new_route" : $new_route ) ] = $route_content;
        unset( $content[ $scope ][ $base_route ][ $new_route ] );
        sanitize_nested( $content, ( $base_route === '/' ? rtrim( $base_route, '/' ) : $base_route ) . ( strpos( $new_route, '/' ) !== 0 ? "/$new_route" : $new_route ), $scope );
    }
}

/**
 * Get arguments from URL
 *
 * @param array $decoded_content The decoded schema content
 * @param string $route The current route (by reference)
 * @param string $scope The scope to search on
 * @return array
 */
function handle_arguments( array $decoded_content, string &$route, string $scope='routes' ) : array {
    $args = array();
    if( empty( $decoded_content[ $scope ] ) ) return $args;
    foreach( $decoded_content[ $scope ] as $current_route => $content ) {
        preg_match( ";^$current_route$;", $route, $match );
        if( empty( $match ) ) continue;
        $args = array_slice( $match, 1 );
        $route = $current_route;
        break;
    }
    return $args;
}

if( ! defined( 'FAKER_SHOW_WARN' ) ) define( 'FAKER_SHOW_WARN', false );

if( empty( $_GET[ 'q' ] ) ) $_GET[ 'q' ] = '/';
$route = $_GET[ 'q' ];

$decoded_schema = json_decode( $json_schema, true );
if( empty( $decoded_schema[ 'routes' ] ) ) json_response( array(
    'message' => 'Schema reading failed. No routes found.'
) );

sanitize_nested( $decoded_schema, '/', 'rules' );
sanitize_nested( $decoded_schema );
$args = handle_arguments( $decoded_schema, $route );

$method = $_SERVER[ 'REQUEST_METHOD' ];
if( ! isset( $decoded_schema[ 'routes' ][ $route ][ $method ] ) ) {
    if( FAKER_SHOW_WARN ) trigger_error( "Route not implemented for $method ($route)", E_USER_WARNING );
    json_response( array(
        'message' => "La route n'est pas implémentée pour la méthode $method ($route)"
    ) );
}
handle_route_rules( $decoded_schema, $route, $method, $args );

// Silence is golden
