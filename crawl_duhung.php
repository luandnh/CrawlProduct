<?php
include('simple_html_dom.php');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($argc == 2) {
    $URL_input = $argv[1];
    print("Crawl product from $URL_input \n");
} else {
    // print("Usage " . $argv[0] . "\n");
    // print("Please input the URL!\n");
    exit(0);
}
date_default_timezone_set('Asia/Ho_Chi_Minh');
$html = new simple_html_dom();
$check_page = true;
$page = 1;
$crawled_link = [];
$data_crawled = [];
print("Get link start! " . date("h:i:sa") . "\n");
while ($check_page) {
    $URL = "$URL_input/page/$page/";
    $html->load_file($URL);
    getProductLink($URL);
    if ($next = $html->find('a[class=next page-numbers]', 0)) {
        $URL = $next->href;
        $html->clear();
        $page += 1;
    } else {
        $check_page = false;
    }
}
print("Get link done! " . date("h:i:sa") . "\n");
print("Export to file start! " . date("h:i:sa") . "\n");
exportToFile();
print("Export to file done! " . date("h:i:sa") . "");
die();



// Get All Product link in page
function getProductLink($URL)
{
    global $crawled_link;
    $html = new simple_html_dom();
    $html->load_file($URL);
    foreach ($html->find('a[class=product-image]') as $element) {
        $product_link = $element->getAttribute('href');
        $crawled_link[] = $product_link;
    }
    $html->clear();
}
function getProductInformation($URL)
{
    global $html, $data_crawled;
    $html->load_file($URL);
    $product_infomation_div     = $html->find('div[class=information]', 0);
    $product_image_a            = $html->find('figure[class=woocommerce-product-gallery__wrapper]', 0)->find('div', 0)->find('a', 0);
    $product_image_source       = (isset($product_image_a)) ? $product_image_a->href : '';
    $product_name_h1            = $product_infomation_div->find('h1[class=product_title entry-title]', 0);
    $product_name               = isset($product_name_h1) ? $product_name_h1->plaintext : '';
    $product_description        = $html->find('div[id=tab-description]', 0)->innertext;
    $product_seo_description    = ($html->find('div[id=tab-description]', 0)->find("p", 0) != null) ? $html->find('div[id=tab-description]', 0)->find("p", 0)->plaintext : "";
    $product_seo_description    = ($product_seo_description != "") ? substr($product_seo_description, 0, 100) : "";
    $product_seo_description    = mb_convert_encoding($product_seo_description,"UTF-8","UTF-8");
    // $product_short_description    = $html->find("div[class=woocommerce-product-details__short-description]", 0)->innertext;
    $product_short_description    = "";
    $product_provider           = "";
    if ($html->find('span[class=yith-wcbr-brands]', 0)) {
        $product_provider           = ($html->find('span[class=yith-wcbr-brands]', 0)->find('a[rel=tag]', 0) != null) ? $html->find('span[class=yith-wcbr-brands]', 0)->find('a[rel=tag]', 0)->plaintext : '';
    }
    $product_tag                = [];
    foreach ($html->find('span[class=posted_in]', 0)->find('a[rel=tag]') as $tag) {
        $product_tag[] = $tag->plaintext;
    }
    $product_type               = current($product_tag);
    if ($html->find('span[class=tagged_as]', 0)) {
        $product_tag                = [];
        foreach ($html->find('span[class=tagged_as]', 0)->find('a[rel=tag]') as $tag) {
            $product_tag[] = explode("/", preg_replace("#^[^:/.]*[:/]+#i", "", $tag->href))[2];
        }
    }
    $product_tags               = implode(",", $product_tag);
    $product_price              = ($product_infomation_div->find('p[class=price] span[class=woocommerce-Price-amount amount]', 0) != null) ? $product_infomation_div->find('span[class=woocommerce-Price-amount amount]', 0)->plaintext : 0;
    $product_price              = preg_replace("(&#8363; )", "", $product_price);
    $product_id                 = $html->find('a[class=compare button]', 0)->getAttribute("data-product_id");
    $product_sku                = 'TD' . $product_id;
    $product_alias              = explode("/", preg_replace("#^[^:/.]*[:/]+#i", "", $URL))[2];
    $arr_tmp                    = array(0, 0, 110, 115);
    $product_price_compare      = floatval(str_replace(".", "", $product_price)) * array_rand($arr_tmp, 1) / 100;
    $product_price_compare      = 0;
    $html->clear();
    $product = (object) [
        'id'                    => $product_id,
        'name'                  => $product_name,
        'alias'                 => $product_alias,
        'image'                 => $product_image_source,
        'short_description'     => $product_short_description,
        'long_description'      => $product_description,
        'seo_description'       => $product_seo_description,
        'tag'                   => $product_tags,
        'type'                  => $product_type,
        'provider'              => $product_provider,
        'price'                 => $product_price,
        'price_compare'         => $product_price_compare,
        'sku'                   => $product_sku
    ];
    $data_crawled[] = $product;
    print("Get product information: " . $product_name . "\n");
}
function exportToFile()
{
    global $crawled_link, $data_crawled;
    $total = count($crawled_link);
    foreach ($crawled_link as $link) {
        getProductInformation($link);
        print("Finish -> " . round((count($data_crawled) / $total) * 100, 2) . "%\n");
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Đường dẫn / Alias');
    $sheet->setCellValue('B1', 'Tên sản phẩm');
    $sheet->setCellValue('C1', 'Nội dung');
    $sheet->setCellValue('D1', 'Nhà cung cấp');
    $sheet->setCellValue('E1', 'Loại');
    $sheet->setCellValue('F1', 'Tags');
    $sheet->setCellValue('G1', 'Hiển thị');
    $sheet->setCellValue('H1', 'Thuộc tính 1(Option1 Name)');
    $sheet->setCellValue('I1', 'Giá trị thuộc tính 1(Option1 Value)');
    $sheet->setCellValue('J1', 'Thuộc tính 2(Option2 Name)');
    $sheet->setCellValue('K1', 'Giá trị thuộc tính 2(Option2 Value)');
    $sheet->setCellValue('L1', 'Thuộc tính 3(Option3 Name)');
    $sheet->setCellValue('M1', 'Giá trị thuộc tính 3(Option2 Value)');
    $sheet->setCellValue('N1', 'Mã (SKU)');
    $sheet->setCellValue('O1', 'Quản lý kho');
    $sheet->setCellValue('P1', 'Số lượng');
    $sheet->setCellValue('Q1', 'Cho phép tiếp tục mua khi hết hàng(continue/deny)');
    $sheet->setCellValue('R1', 'Variant Fulfillment Service');
    $sheet->setCellValue('S1', 'Giá');
    $sheet->setCellValue('T1', 'Giá so sánh');
    $sheet->setCellValue('U1', 'Yêu cầu vận chuyển');
    $sheet->setCellValue('V1', 'VAT');
    $sheet->setCellValue('W1', 'Mã vạch(Barcode)');
    $sheet->setCellValue('X1', 'Ảnh đại diện');
    $sheet->setCellValue('Y1', 'Chú thích ảnh');
    $sheet->setCellValue('Z1', 'Thẻ tiêu đề(SEO Title)');
    $sheet->setCellValue('AA1', 'Thẻ mô tả(SEO Description)');
    $sheet->setCellValue('AB1', 'Cân nặng');
    $sheet->setCellValue('AC1', 'Đơn vị cân nặng');
    $sheet->setCellValue('AD1', 'Ảnh phiên bản');
    $sheet->setCellValue('AE1', 'Mô tả ngắn');
    // $sheet->setCellValue('AF1', 'Id sản phẩm');
    // $sheet->setCellValue('AG1', 'Id tùy chọn');
    $index = 2;
    foreach ($data_crawled as $product) {
        $sheet->setCellValue("A$index", $product->alias);
        $sheet->setCellValue("B$index", $product->name);
        $sheet->setCellValue("C$index", $product->long_description);
        $sheet->setCellValue("D$index", $product->provider);
        $sheet->setCellValue("E$index", $product->type);
        $sheet->setCellValue("F$index", $product->tag);
        $sheet->setCellValue("G$index", 'TRUE');
        $sheet->setCellValue("H$index", '');
        $sheet->setCellValue("I$index", '');
        $sheet->setCellValue("J$index", '');
        $sheet->setCellValue("K$index", '');
        $sheet->setCellValue("L$index", '');
        $sheet->setCellValue("M$index", '');
        $sheet->setCellValue("N$index", $product->sku);
        $sheet->setCellValue("O$index", 'bizweb');
        $sheet->setCellValue("P$index", '100');
        $sheet->setCellValue("Q$index", 'deny');
        $sheet->setCellValue("R$index", '');
        $sheet->setCellValue("S$index", $product->price);
        $sheet->setCellValue("T$index", $product->price_compare);
        $sheet->setCellValue("U$index", 'TRUE');
        $sheet->setCellValue("V$index", 'FALSE');
        $sheet->setCellValue("W$index", '');
        $sheet->setCellValue("X$index", $product->image);
        $sheet->setCellValue("Y$index", '');
        $sheet->setCellValue("Z$index", $product->name);
        $sheet->setCellValue("AA$index", $product->seo_description);
        $sheet->setCellValue("AB$index", '');
        $sheet->setCellValue("AC$index", '');
        $sheet->setCellValue("AD$index", '');
        $sheet->setCellValue("AE$index", $product->short_description);
        // $sheet->setCellValue("AF$index", $product->id);
        // $sheet->setCellValue("AG$index", $product->id);
        $index += 1;
    }
    $writer = new Xlsx($spreadsheet);
    $tmp = (new DateTime())->getTimestamp();
    $writer->save("DuHung-$tmp.xlsx");
}
