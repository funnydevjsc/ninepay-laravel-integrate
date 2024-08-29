<?php

namespace FunnyDev\Ninepay;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class NinepaySdk
{
    private string $method = 'GET';
    private string $uri='';
    private mixed $headers=[];
    private mixed $date='';
    private mixed $params='';
    private mixed $body='';
    public array $url;
    private string $merchant;
    private string $secret;
    private string $sum;
    private string $server;

    public function __construct(string $merchant='', string $secret='', string $sum='', string $server='https://payment.9pay.vn')
    {
        $this->merchant = $this->getConfigValue($merchant, 'merchant');
        $this->secret = $this->getConfigValue($secret, 'secret');
        $this->sum = $this->getConfigValue($sum, 'sum');
        $this->server = $this->getConfigValue($server, 'server');
        $this->url = [
            'create_payment' => [
                'method' => 'POST',
                'url' => '/payments/create'
            ],
            'check_payment'  => [
                'method' => 'GET',
                'url' => '/v2/payments/invoice_code/inquire'
            ]
        ];
    }

    private function getConfigValue($value, $configKey) {
        return $value ? $value : Config::get('ninepay.'.$configKey);
    }

    public function convert_array($data): array
    {
        if (! $data) {
            return [];
        }
        $tmp = json_decode(json_encode($data, true), true);
        if (! is_array($tmp)) {
            $tmp = json_decode($tmp, true);
        }
        return $tmp;
    }

    public function with($date, $uri, $method = 'GET', $headers = []): static
    {
        $this->date = $date;
        $this->uri = $uri;
        $this->method = $method;
        $this->headers = $headers;
        return $this;
    }

    public function withBody($body): static
    {
        if (!is_string($body)) {
            $body = json_encode($body);
        }
        $this->body = $body;

        return $this;
    }

    public function withParams(array $params = []): static
    {
        $this->params = $params;

        return $this;
    }

    public function build(): string
    {
        if ($this->validate()) {
            $canonicalHeaders = $this->canonicalHeaders();

            if ($this->method == 'POST' && $this->body) {
                $canonicalPayload = $this->canonicalBody();
            } else {
                $canonicalPayload = $this->canonicalParams();
            }
            $components = [$this->method, $this->uri, $this->date];
            if ($canonicalHeaders) {
                $components[] = $canonicalHeaders;
            }
            if ($canonicalPayload) {
                $components[] = $canonicalPayload;
            }

            return implode("\n", $components);
        } else {
            return '';
        }
    }

    public static function instance(string $merchant, string $secret, string $sum, string $server='https://payment.9pay.vn'): NinepaySdk
    {
        return new NinepaySdk($merchant, $secret, $sum, $server);
    }

    public function __toString()
    {
        return $this->build();
    }

    protected function validate(): bool
    {
        if (empty($this->uri) || empty($this->date)) {
            return false;
        }
        return true;
    }

    protected function canonicalHeaders(): string
    {
        if (!empty($this->headers)) {
            ksort($this->headers);
            return http_build_query($this->headers);
        } else {
            return '';
        }
    }

    protected function canonicalParams(): string
    {
        $str = '';
        if (!empty($this->params)) {
            ksort($this->params);
            foreach ($this->params as $key => $val) {
                $str .= urlencode($key) . '=' . urlencode($val) . '&';
            }
            $str = substr($str, 0, -1);
        }

        return $str;
    }

    protected function canonicalBody(): string
    {
        if (!empty($this->body)) {
            return base64_encode(hash('sha256', $this->body, true));
        }
        return '';
    }

    public function signature(array $data, string $method='POST', string $url='', string $return_url=''): string
    {
        $response = $method . "\n" . $url . "\n" . strval(time()) . "\n";
        if (!empty($data)) {
            foreach ($data as $c => $v) {
                $response .= $c . "=" . $v . "&";
            }
        }
        if ($return_url) {
            $response .= "return_url=" . $return_url;
        }
        return $response;
    }

    private function encrypt_data(string $input): string
    {
        $sha256 = hash_hmac('sha256', $input, $this->secret, true);
        return base64_encode($sha256);
    }

    public function verify_result(string $signature, string $result): string
    {
        $hashChecksum = strtoupper(hash('sha256', $result . $this->sum));
        return !strcmp($hashChecksum, $signature);
    }

    public function read_result(string $message, string $sum): array
    {
        $result = [
            'status' => false,
            'amount' => 0,
            'message' => 'Unknown error',
            'description' => ''
        ];
        if (!empty($message) && !empty($sum)) {
            if ($this->verify_result($sum, $message)) {
                $data = $this->convert_array(base64_decode($message));
                if ($data['status'] == 5) {
                    $result['description'] = $data['description'];
                    $result['status'] = true;
                    $result['message'] = 'Payment success. Please wait up to 5 minutes for getting your invoice ready';
                } else {
                    $failed = Session::get('ninepay_failed') ? Session::get('ninepay_failed') + 1 : 1;
                    Session::put('ninepay_failed', $failed);
                    $result['message'] = match ($data['status']) {
                        8 => 'Your payment has been cancelled',
                        6 => 'Your payment has been failed',
                        15 => 'Your payment was expired',
                        default => 'Your payment is waiting for process',
                    };
                }
            } else {
                $hacked = Session::get('ninepay_hacked') ? Session::get('ninepay_hacked') + 1 : 1;
                $result['message'] = 'Trying to fake payment result';
                Session::put('ninepay_hacked', $hacked);
            }
        }
        return $result;
    }

    public function create_payment(string $invoice_number, int $amount, string $description, string $return_url='', string $back_url=''): string
    {
        $time = time();
        $params = [
            'merchantKey' => $this->merchant,
            'time' => $time,
            'invoice_no' => $invoice_number,
            'amount' => $amount,
            'description' => $description,
            'back_url' => $back_url,
            'return_url' => $return_url
        ];
        $message = NinepaySdk::instance($this->merchant, $this->secret, $this->sum, $this->server)
            ->with($time, $this->server . $this->url['create_payment']['url'], $this->url['create_payment']['method'])
            ->withParams($params)
            ->build();
        $signature = $this->encrypt_data($message);
        $httpData = [
            'baseEncode' => base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE)),
            'signature' => $signature,
        ];
        return $this->server . '/portal?' . http_build_query($httpData);
    }

    public function check_payment(string $invoice_number): array
    {
        $result = [
            'status' => false,
            'amount' => 0,
            'message' => 'Unknown error',
            'description' => ''
        ];
        try {
            $time = time();
            $url = str_replace('invoice_code', $invoice_number, $this->server . $this->url['check_payment']['url']);
            $message = NinepaySdk::instance($this->merchant, $this->secret, $this->sum, $this->server)
                ->with($time, $url, $this->url['check_payment']['method'])
                ->withParams([])
                ->build();
            $signature = $this->encrypt_data($message);
            $headers = [
                'Authorization' => 'Signature Algorithm=HS256,Credential=' . $this->merchant . ',SignedHeaders=,Signature=' . $signature,
                'Date' => $time,
            ];
            $response = Http::withHeaders($headers)->timeout(10)->get(str_replace('invoice_code', $invoice_number, $this->server . $this->url['check_payment']['url']));
            $data = $response->json();
            if ($data['status'] == 5) {
                $result['description'] = $data['description'];
                $result['status'] = true;
                $result['amount'] = $data['amount'];
                $result['message'] = 'Payment successfully from 9Pay';
            } else {
                $failed = Session::get('ninepay_failed') ? Session::get('ninepay_failed') + 1 : 1;
                Session::put('ninepay_failed', $failed);
                $result['message'] = match ($data['status']) {
                    8 => 'Your payment has been cancelled',
                    6 => 'Your payment has been failed',
                    15 => 'Your payment was expired',
                    default => 'Your payment is waiting for process',
                };
            }
        } catch (\Exception) {}
        return $result;
    }
}
