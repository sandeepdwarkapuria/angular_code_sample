<?php
namespace Ompty\Service;
use Ompty\Utility;
use Ompty\Dao\BidDao;
use Ompty\Dao\LocationDao;
use Ompty\Service\OmptypassService;
use Input,
    Validator;
class BidService extends Base {
    /*
     * variable for Bid Dao
     */
    protected $BidDao;
    /*
     * variable for Location Dao
     */
    protected $LocationDao;
    /*
     * variable for Ompty Pass Serivce
     */
    protected $OmptyPassService;
    public function __construct() {
        $this->bidDao = $this->bidDao();
        $this->locationDao = $this->locationDao();
        $this->omptyPassDao = $this->omptyPassDao();
    }
    public function createBid($input) {
        $validation = $this->validateBidData($input);
        if ($validation->fails()) {
            return $validation->messages();
        } else {
            $data['bid_date'] = $input['date'] . ' ' . $input['time'];
            $data['user_id'] = \Auth::user()->id();
            $data['total_guests'] = $input['people'];
            $data['suburb_id'] = $input['suburb_id'];
            $data['price_range_start'] = $input['price_range_start'];
            $data['price_range_end'] = $input['price_range_end'];
            $conflicting_passes = $this->bidBlocker(\Auth::user()->id(), $data);
//           print_r($conflicting_passes);exit;
            if (empty($conflicting_passes)) { //putting up bid blocker if true then no need to block
                if ($bid_id = $this->bidDao->storeBid($data)) {
                    return $bid_id;
                }
            } else {
                return $conflicting_passes;
//                return \Redirect::to('bid/create')->with('message', 'Bid Creation Failed You Already Have bookings on these timings')
//                                ->with('passes', $conflicting_passes)->withInput(\Input::except('suburb'));
            }
        }
    }
    public function validateBidData($data) {
        Validator::extend('if_suburb_id_exist', function($field, $value, $parameter) {
//            return in_array($value, array_column($this->suburb_service->getAllSuburbs(), 'suburb_id'));
            $suburbs = $this->locationDao->getAllSuburbsAsArray();
            $validation = false;
            foreach ($suburbs as $suburb) {
                if (in_array($value, $suburb)) {
                    $validation = true;
                } else {
                    $validation;
                }
            }
            return $validation;
        });
        $rules = array(
            'date' => 'required', // just a normal required validation
            'time' => 'required', // just a normal required validation
            'people' => 'required', // just a normal required validation
            'price_range_start' => 'required', // just a normal required validation
            'price_range_end' => 'required', // just a normal required validation
            'suburb_id' => 'required|if_suburb_id_exist'  // validation to check if suburb exist
        );
        $messages = array(
            "suburb_id.if_suburb_id_exist" => "Wrong suburb selection please try again!",
            "suburb_id.required" => "Suburb is required"
        );
        return Validator::make($data, $rules, $messages);
    }
    public function bidBlocker($user_id, $bid_data) {
        $tmp =  $this->bidDao->getBidsByUserId( $user_id, array('bid_id')  ) ;
        $bids = null;
        foreach ($tmp as $singleIndex) {
           $bids.= $singleIndex['bid_id'].',';
        }
        if ($bids) {
            $user_all_bids = rtrim($bids,',');
            return $this->omptyPassDao->getOmptyPassByUserBidsAndDateTime($user_all_bids, $bid_data['bid_date']);
        } else {
            return array();
        }
    }
    public function getBidsByUserId($user_id, $columns) {
        return $this->bidDao->getBidsByUserId($user_id, $columns);
    }
}