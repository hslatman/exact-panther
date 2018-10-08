<?php
/**
 * Author: Herman Slatman
 * Date: 08/10/2018
 * Time: 20:07
 */

namespace App\Service;

use Carbon\Carbon;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\Client;

use OTPHP\TOTP;

class ExactService
{
    /** @var Client $client */
    private $client;

    /** @var string $username */
    private $username;

    /** @var string $password */
    private $password;

    /** @var string $otp_seed */
    private $otp_seed;

    public function __construct(string $username, string $password, string $otp_seed)
    {
        $this->username = $username;
        $this->password = $password;
        $this->otp_seed = $otp_seed;

        $this->client = Client::createChromeClient();
    }

    public function go() {

        $this->client->request('GET', 'https://start.exactonline.nl');

        // Wait for an element to be rendered
        $this->client->waitFor('#LoginForm_UserName');
        $login_button = $this->client->findElement(WebDriverBy::id('LoginForm_btnSave'));


        $user_field = $this->client->findElement(WebDriverBy::id('LoginForm_UserName'));
        $user_field->sendKeys($this->username);
        $pass_field = $this->client->findElement(WebDriverBy::id('LoginForm_Password'));
        $pass_field->sendKeys($this->password);


        $login_button->click();
        $this->client->waitFor('#LoginForm_Input_Key');

        $otp = TOTP::create($this->otp_seed);

        $otp_field = $this->client->findElement(WebDriverBy::id('LoginForm_Input_Key'));
        $otp_field->sendKeys((string) $otp->now());

        $key_button = $this->client->findElement(WebDriverBy::id('LoginForm_btnSave'));
        $key_button->click();


        $iframe = $this->client->findElement(WebDriverBy::id('MainWindow'));
        $iframe_src_attribute = $iframe->getAttribute('src');
        $division = parse_url($iframe_src_attribute)['query'];
        $next_page = 'https://start.exactonline.nl/docs/ProRepScheduleAccountability.aspx?' . $division;
        $this->client->request('GET', $next_page);


        $previous_monday = (new Carbon())->previous(Carbon::MONDAY);
        $previous_sunday = (clone $previous_monday)->next(Carbon::SUNDAY);


        // Bit hacky, but sendKeys does not work :-( Send the From, To and <Traject> fields
        $this->client->executeScript(
            sprintf('
                var s = document.getElementById("CritDate_From");
                s.value = "%s";
        ', $previous_monday->format('d-m-Y')));

        $this->client->executeScript(
            sprintf('
                var s = document.getElementById("CritDate_To");
                s.value = "%s";
        ', $previous_sunday->format('d-m-Y')));

        $this->client->executeScript(
            '
                var s = document.getElementById("CritDate_Selection");
                s.value = 0;
        ');

        $refresh_button = $this->client->findElement(WebDriverBy::id('btnRefresh'));
        $refresh_button->click();

        $employee_list = $this->client->findElement(WebDriverBy::id('List_lv_ListViewContainer'));
        $employee_rows = $employee_list->findElements(WebDriverBy::cssSelector('table#List_lv.ListView > tbody > tr'));

        $employee_rows = array_slice($employee_rows, 1, count($employee_rows) - 3);

        $result = [];

        /** @var RemoteWebElement $row */
        foreach ($employee_rows as $row) {
            list($name, $rooster, $ingediend, $definitief, $verzuim, $verlof, $verantwoording) = $row->findElements(WebDriverBy::xpath('.//td'));

            $is_ingevuld = true;
            $is_nagekeken = true;

            if ((int)$verantwoording->getText() < 100) {
                $is_ingevuld = false;
            } else {
                $totaal = (float)$definitief->getText() + (float)$verzuim->getText() + (float)$verlof->getText();
                if ((float)$totaal < (float)$rooster->getText()) {
                    $is_nagekeken = false;
                }
            }

            $result[] = [$name->getText(), $is_ingevuld, $is_nagekeken, 'manager'];

        }

        //$this->client->waitFor('#END', 600);


        return $result;





        $this->client->waitFor('#END', 600);


    }
}