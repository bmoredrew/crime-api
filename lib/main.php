<?php
namespace CrimeAPI;

class API_Router 
{
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
            case 'crime-counts' :
                $sql = "
                    SELECT 
                        COUNT(*) as total,
                        crime_name2 as crime_name
                    FROM
                        incidents
                    WHERE
                        crime_name2 <> ''
                    GROUP BY crime_name2
                    ORDER BY crime_name2
                ";
                $results = $wpdb->get_results( $sql );
                $final = [];
                foreach ( $results as $item )
                {
                    $final[ $item->crime_name ] = (int) $item->total;
                }
                $this->json_response( $final, "crime-counts.json" );
                break;

            case 'crime-type-address' :
                if ( !( $_GET['crime-type'] ?? false ) ) $this->json_response( [] );
                
                $sql = "
                    SELECT
                        incident_id,
                        block_address,
                        crime_name1,
                        crime_name2,
                        crime_name3,
                        city,
                        state,
                        zip,
                        place 
                    FROM
                        incidents
                    WHERE 
                        crime_name1 LIKE %s
                        OR crime_name2 LIKE %s
                        OR crime_name3 LIKE %s
                    ORDER BY
                        incident_id ASC
                ";
                $sql = $wpdb->prepare( $sql, $_GET['crime-type'], $_GET['crime-type'], $_GET['crime-type'] );
                $results = $wpdb->get_results( $sql );
                $this->json_response([
                    'total_records' => (int) \count( $results ),
                    'district' => $results
                ]);
                break;

            case 'crimes-in-district' :
                if ( !( $_GET['district'] ?? false ) ) $this->json_response( [] );
                $sql = "SELECT COUNT( * ) FROM incidents WHERE LOWER( police_district_name ) = LOWER( %s ) ORDER BY incident_id ASC";
                $sql = $wpdb->prepare( $sql, $_GET['district'] );
                $total = $wpdb->get_var( $sql );
                $this->json_response([
                    'total_records' => (int) $total,
                    'district' => $_GET['district']
                ]);
                break;

            case 'police_district_name' :
            case 'crime_name3' :
            case 'crime_name2' :
            case 'crime_name1' :
                $sql = "SELECT DISTINCT %s FROM incidents WHERE %s <> '' ORDER BY crime_name1 ASC;";
                $sql = \sprintf( $sql, $_GET['get'], $_GET['get'] );
                $results = $wpdb->get_col( $sql );
                $this->json_response( $results );
                break;

            case 'import' :
                $this->import();
                break;
        }
        die;
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
            'incident_id',
            'offence_code',
            'cr_number',
            'datetime_dispatch',
            'nibrs_code',
            'victims',
            'crime_name1',
            'crime_name2',
            'crime_name3',
            'police_district_name',
            'block_address',
            'city',
            'state',
            'zip',
            'agency',
            'place',
            'sector',
            'beat',
            'pra',
            'address_number',
            'street_prefix',
            'street_name',
            'street_suffix',
            'street_type',
            'datetime_start',
            'datetime_end',
            'latitude',
            'longitude',
            'police_district_number',
            'incident_location'
        ];

        $file = __DIR__ . '/../_meta/crime.csv';

        $row = 0;
        if ( $f = \fopen( $file, 'rb' ) )
        {
            while ( $line = \fgetcsv( $f, 2048 ) )
            {
                $row++;

                if ( $row == 1 ) continue;

                $data = \array_combine( $columns, $line );
                $data['datetime_dispatch'] = \date_i18n( 'YmdHis', \strtotime( $data['datetime_dispatch'] ) );
                $data['datetime_start'] = \date_i18n( 'YmdHis', \strtotime( $data['datetime_start'] ) );
                $data['datetime_end'] = \date_i18n( 'YmdHis', \strtotime( $data['datetime_end'] ) );
                $wpdb->insert( 'incidents', $data );

                if ( $row > self::MAX_IMPORT_LENGTH ) break;
            }
            
            \fclose( $f );

            echo '<h2>Completed with import of ' . $row . ' rows of CSV data.</h2>';
        }
    }

}

API_Router::init();