<?php
namespace CrimeAPI;

class API_Router 
{
    const API_RESULT_PAGE_LIMIT = 100;
    const MAX_IMPORT_LENGTH = 15000;

    public static $instance;

    public static function init()
    {
        null === self::$instance && self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        \add_action( 'init', [ $this, 'route' ], 1 );
    }

    public function route()
    {
        if ( !$this->validate_request() ) return;

        global $wpdb;

        switch ( $_GET['get'] ?? false )
        {
            case 'address-by-type' :
                $crime_code_description = $_GET['crime_desc'] ?? false;

                if ( !$crime_code_description ) return $this->json_response([
                    'success' => false,
                    'message' => 'Please pass the crime code description field.'
                ]);

                $limit = self::API_RESULT_PAGE_LIMIT;
                $paging = $this->generate_paging_clause( $_GET['page'] ?? 1, $limit );

                $sql = "
                    SELECT SQL_CALC_FOUND_ROWS
                        incident_id, dr_no, location_name, location_cross_street, latitude, longitude
                    FROM
                         incidents
                    WHERE
                        LOWER( crime_code_description ) LIKE LOWER( %s )
                    ORDER BY date_reported DESC
                    $paging
                ";
                $sql = $wpdb->prepare( $sql, $crime_code_description );
                $results = $wpdb->get_results( $sql );
                break;

            case 'crimes-in-area' :
                $area_name = $_GET['area'] ?? false;

                if ( !$area_name ) return $this->json_response([
                    'success' => false,
                    'message' => 'Property field empty.'
                ]);

                $limit = self::API_RESULT_PAGE_LIMIT;
                $paging = $this->generate_paging_clause( $_GET['page'] ?? 1, $limit );

                $sql = "
                    SELECT SQL_CALC_FOUND_ROWS
                        *
                    FROM
                         incidents
                    WHERE
                        LOWER( area_name ) LIKE LOWER( %s )
                    ORDER BY date_reported DESC
                    $paging
                ";
                $sql = $wpdb->prepare( $sql, $area_name );
                $results = $wpdb->get_results( $sql );
                break;

            case 'all' :
                $column = \strtolower( $_GET['property'] ?? false );

                if ( !$column ) return $this->json_response([
                    'success' => false,
                    'message' => 'Property field empty.'
                ]);

                // confirm column
                $table_desc = $wpdb->get_results( 'DESCRIBE incidents' );
                $columns = [];

                foreach ( $table_desc as $col )
                    $columns[ \strtolower( $col->Field ) ] = $col->Field;

                if ( !isset( $columns[ $column ] ) ) return $this->json_response([
                    'success' => false,
                    'message' => 'Invalid property name.'
                ]);

                $column = $columns[ $column ];

                $limit = self::API_RESULT_PAGE_LIMIT;
                $paging = $this->generate_paging_clause( $_GET['page'] ?? 1, $limit );

                $sql = "
                    SELECT SQL_CALC_FOUND_ROWS
                        DISTINCT $column 
                    FROM
                         incidents
                    ORDER BY $column ASC
                    $paging
                ";

                $results = $wpdb->get_col( $sql );
                break;

            case 'import' :
                return $this->import();
                break;

            default :
                return $this->json_response([
                    'success' => false,
                    'message' => 'Invalid request.'
                ]);
                break;
        }

        $found_rows = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

        return $this->json_response([
            'request' => $_GET,
            'success' => true,
            'page' => \intval( $_GET['page'] ?? 1 ),
            'total_pages' => \ceil( $found_rows / $limit ),
            'results_per_page' => (int) $limit,
            'found_results' => $found_rows,
            'results' => $results
        ]);
        die;
    }

    private function generate_paging_clause( $page, $limit = self::API_RESULT_PAGE_LIMIT )
    {
        global $wpdb;

        if ( ! $page ) $page = 1;
        $paging = "LIMIT %d, %d";
        $start = ( $page - 1 ) * (int) $limit;

        return $wpdb->prepare( $paging, $start, $limit );
    }

    private function json_response( $data, $filename = false )
    {
        \header( 'Content-Type: text/json' );
        if ( $filename )
        {
            \header( 'Content-Disposition: attachment; filename="' . \basename( $filename ) . '"' );
        }
        echo \json_encode( $data );
        die;
    }

    private function validate_request()
    {
        $url = \site_url( $_SERVER['REQUEST_URI'] );
        $parts = \parse_url( $url );

        return ( 'crime-api' == \trim( $parts['path'], '/' ) );
    }

    private function import()
    {
        global $wpdb;

        $columns = [
            'dr_no',
            'date_reported',
            'date_occ',
            'time_occ',
            'area_code',
            'area_name',
            'report_district_no',
            'part12',
            'crime_code',
            'crime_code_description',
            'mo_codes',
            'victim_age',
            'victim_sec',
            'victim_descent',
            'premis_code',
            'premis_description',
            'weapon_code',
            'weapon_description',
            'crime_status',
            'crime_status_description',
            'crime_code1',
            'crime_code2',
            'crime_code3',
            'crime_code4',
            'location_name',
            'location_cross_street',
            'latitude',
            'longitude',
        ];

        $file = __DIR__ . '/../_meta/crime-la.csv';

        $row = 0;
        if ( $f = \fopen( $file, 'rb' ) )
        {
            while ( $line = \fgetcsv( $f, 2048 ) )
            {
                $row++;
                if ( $row == 1 ) continue;

                $data = \array_combine( $columns, $line );
                
                $data['incident_id'] = '';
                $data['date_occ'] = \date_i18n( 'Ymd', \strtotime( $data['date_occ'] ) );
                $data['date_reported'] = \date_i18n( 'Ymd', \strtotime( $data['date_reported'] ) );
                
                if ( \strlen( $data['time_occ'] ) < 4 )
                    $data['time_occ'] = '0' . $data['time_occ'];
                    
                $data['time_occ'] = \substr( $data['time_occ'], 0, 2 ) . ':' . \substr( $data['time_occ'], -2 );
                $data['time_occ'] = \date_i18n( 'His', \strtotime( '2022-01-01 ' . $data['date_occ'] ) );
                
                $wpdb->insert( 'incidents', $data );

                if ( $row > self::MAX_IMPORT_LENGTH ) break;
            }
            
            \fclose( $f );

            echo '<h2>Completed with import of ' . $row . ' rows of CSV data.</h2>';
        }
    }

}

API_Router::init();