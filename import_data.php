<?php

// Подключение модуля интернет-магазина Битрикс
CModule::IncludeModule("catalog");
CModule::IncludeModule("iblock");

// Параметры API Al-Style
$apiEndpoint = "";
$accessToken = ""; // Замените на ваш токен

// Функция для выполнения запроса к API
function apiRequest($method, $params = []) {
    global $apiEndpoint, $accessToken;
    $url = $apiEndpoint . $method . "?access-token=" . $accessToken;
    if (!empty($params)) {
        $url .= "&" . http_build_query($params);
    }
    $response = file_get_contents($url);
    sleep(5); // Задержка между запросами
    return json_decode($response, true);
}

// Товары

// Актуальность данных
function checkDataValidity() {
    $response = apiRequest("date");
    if ($response) {
        echo "Актуальность данных: Дата и время актуальны на " . $response . "\n";
    } else {
        echo "Ошибка проверки актуальности данных\n";
    }
}

// Список категорий
function syncCategories($iblockId) {
    $categories = apiRequest("categories");
    if ($categories) {
        foreach ($categories as $category) {
            $arFields = [
                "NAME" => $category["name"],
                "IBLOCK_ID" => $iblockId,
                "EXTERNAL_ID" => $category["id"],
                "DEPTH_LEVEL" => $category["level"],
                "DATE_MODIFIED" => date("Y-m-d H:i:s") // Добавляем дату последнего изменения
            ];
            $el = new CIBlockSection;
            $sectionId = getSectionIdByExternalId($category["id"], $iblockId);

            if ($sectionId) {
                // Обновление существующей категории
                $res = $el->Update($sectionId, $arFields);
                if ($res) {
                    echo "Категория обновлена: " . $category["name"] . "\n";
                } else {
                    echo "Ошибка при обновлении категории: " . $el->LAST_ERROR . "\n";
                }
            } else {
                // Добавление новой категории
                $res = $el->Add($arFields);
                if ($res) {
                    echo "Категория создана: " . $category["name"] . "\n";
                } else {
                    echo "Ошибка при создании категории: " . $el->LAST_ERROR . "\n";
                }
            }
        }
    } else {
        echo "Ошибка получения списка категорий\n";
    }
}

// Функция для получения ID раздела по внешнему ID
function getSectionIdByExternalId($externalId, $iblockId) {
    $filter = [
        "IBLOCK_ID" => $iblockId,
        "EXTERNAL_ID" => $externalId
    ];
    $res = CIBlockSection::GetList([], $filter, false, ["ID"]);
    if ($section = $res->Fetch()) {
        return $section["ID"];
    }
    return false;
}

// Список брендов
function syncBrands($iblockId) {
    $response = apiRequest("brands");

    if ($response && $response["status"] && isset($response["data"])) {
        $brands = $response["data"];
        foreach ($brands as $brand) {
            // Проверьте, существует ли уже бренд в инфоблоке
            $existingBrandId = getElementIdByExternalId($brand["id"], $iblockId);

            $arFields = [
                "NAME" => $brand["name"],
                "DATE_MODIFIED" => date("Y-m-d H:i:s") // Добавляем дату последнего изменения
            ];

            $el = new CIBlockElement;
            if ($existingBrandId) {
                // Обновление существующего элемента
                $res = $el->Update($existingBrandId, $arFields);
                if ($res) {
                    echo "Бренд обновлен: " . $brand["name"] . "\n";
                } else {
                    echo "Ошибка при обновлении бренда: " . $el->LAST_ERROR . "\n";
                }
            } else {
                // Создание нового элемента
                $arFields["IBLOCK_ID"] = $iblockId;
                $arFields["EXTERNAL_ID"] = $brand["id"]; // Замените на поле для хранения внешнего ID

                $res = $el->Add($arFields);
                if ($res) {
                    echo "Бренд создан: " . $brand["name"] . "\n";
                } else {
                    echo "Ошибка при создании бренда: " . $el->LAST_ERROR . "\n";
                }
            }
        }
    } else {
        echo "Ошибка получения списка брендов\n";
    }
}

// Функция для получения ID элемента по внешнему ID
function getElementIdByExternalId($externalId, $iblockId) {
    $filter = [
        "IBLOCK_ID" => $iblockId,
        "PROPERTY_EXTERNAL_ID" => $externalId // Замените на поле для хранения внешнего ID
    ];
    $res = CIBlockElement::GetList([], $filter, false, false, ["ID"]);
    if ($element = $res->Fetch()) {
        return $element["ID"];
    }
    return false;
}

// Список товаров
function syncProducts($iblockId) {
    // Параметры запроса
    $params = [
        'limit' => 250, // Количество выдаваемых элементов
        'exclude_missing' => true, // Скрыть отсутствующие
        'additional_fields' => 'description,brand,weight,warranty,images,url,barcode,reducedprice,expectedArrivalDate,discountPrice,discount,certificates,instructions,reservationTime,reservationDate,defectDescription,rrp,warehouse,marketplaceArticles,tnved'
    ];

    // Запрос к API для получения товаров
    $elements = apiRequest("/elements", $params);

    if ($elements) {
        foreach ($elements as $element) {
            $arFields = [
                "NAME" => $element["name"],
                "IBLOCK_ID" => $iblockId,
                "XML_ID" => $element["article"],
                "PROPERTY_VALUES" => [
                    "ARTICLE_PN" => $element["article_pn"],
                    "FULL_NAME" => $element["full_name"],
                    "PRICE" => $element["price1"],
                    "QUANTITY" => $element["quantity"],
                    "DESCRIPTION" => $element["description"] ?? '',
                    "BRAND" => $element["brand"] ?? '',
                    "WEIGHT" => $element["weight"] ?? '',
                    "WARRANTY" => $element["warranty"] ?? '',
                    "IMAGES" => $element["images"] ?? '',
                    "URL" => $element["url"] ?? '',
                    "BARCODE" => $element["barcode"] ?? '',
                    "REDUCEDPRICE" => $element["reducedprice"] ?? '',
                    "EXPECTEDARRIVALDATE" => $element["expectedArrivalDate"] ?? '',
                    "DISCOUNTPRICE" => $element["discountPrice"] ?? '',
                    "DISCOUNT" => $element["discount"] ?? '',
                    "CERTIFICATES" => $element["certificates"] ?? '',
                    "INSTRUCTIONS" => $element["instructions"] ?? '',
                    "RESERVATIONTIME" => $element["reservationTime"] ?? '',
                    "RESERVATIONDATE" => $element["reservationDate"] ?? '',
                    "DEFECTDESCRIPTION" => $element["defectDescription"] ?? '',
                    "RRP" => $element["rrp"] ?? '',
                    "WAREHOUSE" => $element["warehouse"] ?? '',
                    "MARKETPLACEARTICLES" => $element["marketplaceArticles"] ?? '',
                    "TNVED" => $element["tnved"] ?? ''
                ]
            ];

            $el = new CIBlockElement;
            $existingProductId = getProductIdByArticle($element["article"], $iblockId);

            if ($existingProductId) {
                $res = $el->Update($existingProductId, $arFields);
                if ($res) {
                    echo "Товар обновлен: " . $element["name"] . "\n";
                } else {
                    echo "Ошибка при обновлении товара: " . $el->LAST_ERROR . "\n";
                }
            } else {
                $res = $el->Add($arFields);
                if ($res) {
                    echo "Товар создан: " . $element["name"] . "\n";
                } else {
                    echo "Ошибка при создании товара: " . $el->LAST_ERROR . "\n";
                }
            }
        }
    } else {
        echo "Ошибка получения списка товаров\n";
    }
}

// Функция для получения ID товара по артикулу
function getProductIdByArticle($article, $iblockId) {
    $filter = [
        "IBLOCK_ID" => $iblockId,
        "XML_ID" => $article
    ];
    $res = CIBlockElement::GetList([], $filter, false, false, ["ID"]);
    if ($element = $res->Fetch()) {
        return $element["ID"];
    }
    return false;
}

// Список товаров с данными постраничности
function syncProductsWithPagination($iblockId, $categories = [], $brands = []) {
    $offset = 0;
    $limit = 100;
    $excludeMissing = true; // Скрыть отсутствующие
    $additionalFields = 'description,brand,weight,warranty,images,url,barcode,reducedprice,expectedArrivalDate,discountPrice,discount,certificates,instructions,reservationTime,reservationDate,defectDescription,rrp,warehouse,marketplaceArticles,tnved';

    do {
        $params = [
            'limit' => $limit,
            'offset' => $offset,
            'exclude_missing' => $excludeMissing,
            'additional_fields' => $additionalFields
        ];

        if (!empty($categories)) {
            $params['category'] = implode(',', $categories);
        }

        if (!empty($brands)) {
            $params['brand'] = implode(',', $brands);
        }

        $response = apiRequest("/elements-pagination", $params);

        if (isset($response["elements"]) && isset($response["pagination"])) {
            $elements = $response["elements"];
            $totalCount = $response["pagination"]["totalCount"];

            // Создание или обновление товаров в Битрикс
            foreach ($elements as $element) {
                $arFields = [
                    "NAME" => $element["name"],
                    "IBLOCK_ID" => $iblockId,
                    "XML_ID" => $element["article"],
                    "PROPERTY_VALUES" => [
                        "ARTICLE_PN" => $element["article_pn"],
                        "FULL_NAME" => $element["full_name"],
                        "PRICE" => $element["price1"],
                        "QUANTITY" => $element["quantity"],
                        "DESCRIPTION" => $element["description"] ?? '',
                        "BRAND" => $element["brand"] ?? '',
                        "WEIGHT" => $element["weight"] ?? '',
                        "WARRANTY" => $element["warranty"] ?? '',
                        "IMAGES" => $element["images"] ?? '',
                        "URL" => $element["url"] ?? '',
                        "BARCODE" => $element["barcode"] ?? '',
                        "REDUCEDPRICE" => $element["reducedprice"] ?? '',
                        "EXPECTEDARRIVALDATE" => $element["expectedArrivalDate"] ?? '',
                        "DISCOUNTPRICE" => $element["discountPrice"] ?? '',
                        "DISCOUNT" => $element["discount"] ?? '',
                        "CERTIFICATES" => $element["certificates"] ?? '',
                        "INSTRUCTIONS" => $element["instructions"] ?? '',
                        "RESERVATIONTIME" => $element["reservationTime"] ?? '',
                        "RESERVATIONDATE" => $element["reservationDate"] ?? '',
                        "DEFECTDESCRIPTION" => $element["defectDescription"] ?? '',
                        "RRP" => $element["rrp"] ?? '',
                        "WAREHOUSE" => $element["warehouse"] ?? '',
                        "MARKETPLACEARTICLES" => $element["marketplaceArticles"] ?? '',
                        "TNVED" => $element["tnved"] ?? ''
                    ]
                ];

                $el = new CIBlockElement;
                $existingProductId = getProductIdByArticle($element["article"], $iblockId);

                if ($existingProductId) {
                    $res = $el->Update($existingProductId, $arFields);
                    if ($res) {
                        echo "Товар обновлен: " . $element["name"] . "\n";
                    } else {
                        echo "Ошибка при обновлении товара: " . $el->LAST_ERROR . "\n";
                    }
                } else {
                    $res = $el->Add($arFields);
                    if ($res) {
                        echo "Товар создан: " . $element["name"] . "\n";
                    } else {
                        echo "Ошибка при создании товара: " . $el->LAST_ERROR . "\n";
                    }
                }
            }

            $offset += $limit;
        } else {
            echo "Ошибка получения списка товаров\n";
            break;
        }
    } while ($offset < $totalCount);
}

// Информация о товаре
function getProductInfo($articleList) {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    // Параметры запроса
    $params = [
        'article' => implode(',', $articleList),
        'additional_fields' => 'description,brand,weight,warranty,images,url,barcode,reducedprice,expectedArrivalDate,discountPrice,discount,certificates,instructions,reservationTime,reservationDate,defectDescription,rrp,warehouse,marketplaceArticles,tnved,detailText,properties'
    ];

    // Выполнение запроса к API
    $response = apiRequest("element-info", $params);

    if ($response && is_array($response)) {
        foreach ($response as $info) {
            if (isset($info['article'])) {
                echo "Информация о товаре для артикула " . $info["article"] . ":\n";
                echo "Название: " . $info["name"] . "\n";
                echo "Полное название: " . $info["full_name"] . "\n";
                echo "Категория: " . $info["category"] . "\n";
                echo "Цена дилерская: " . $info["price1"] . "\n";
                echo "Цена розничная: " . $info["price2"] . "\n";
                echo "Остаток на складе: " . $info["quantity"] . "\n";
                echo "Новинка: " . ($info["isnew"] ? 'Да' : 'Нет') . "\n";
                echo "Артикул (Part Number): " . $info["article_pn"] . "\n";
                echo "Описание: " . ($info["description"] ?? 'Не указано') . "\n";
                echo "Бренд: " . ($info["brand"] ?? 'Не указан') . "\n";
                echo "Вес: " . ($info["weight"] ?? 'Не указан') . "\n";
                echo "Гарантия: " . ($info["warranty"] ?? 'Не указана') . "\n";
                echo "Изображения: " . ($info["images"] ?? 'Нет') . "\n";
                echo "Ссылка: " . ($info["url"] ?? 'Не указана') . "\n";
                echo "Штрихкод: " . ($info["barcode"] ?? 'Не указан') . "\n";
                echo "Снижена цена: " . ($info["reducedprice"] ?? 'Не указана') . "\n";
                echo "Ожидаемая дата поступления: " . ($info["expectedArrivalDate"] ?? 'Не указана') . "\n";
                echo "Цена со скидкой: " . ($info["discountPrice"] ?? 'Не указана') . "\n";
                echo "Процент скидки: " . ($info["discount"] ?? 'Не указан') . "\n";
                echo "Сертификаты: " . ($info["certificates"] ?? 'Не указаны') . "\n";
                echo "Инструкции: " . ($info["instructions"] ?? 'Не указаны') . "\n";
                echo "Период резерва: " . ($info["reservationTime"] ?? 'Не указан') . "\n";
                echo "Окончание резерва: " . ($info["reservationDate"] ?? 'Не указана') . "\n";
                echo "Описание дефекта уценки: " . ($info["defectDescription"] ?? 'Не указано') . "\n";
                echo "Контроль розничной цены: " . ($info["rrp"] ?? 'Не указан') . "\n";
                echo "Склад: " . ($info["warehouse"] ?? 'Не указан') . "\n";
                echo "Артикулы на маркетплейсах: " . ($info["marketplaceArticles"] ?? 'Не указаны') . "\n";
                echo "Код ТНВЭД: " . ($info["tnved"] ?? 'Не указан') . "\n";
                echo "Детальное описание: " . ($info["detailText"] ?? 'Не указано') . "\n";
                echo "Свойства товара: " . ($info["properties"] ?? 'Не указаны') . "\n";
                echo "---------------------------------\n";
            }
        }
    } else {
        echo "Ошибка получения информации о товаре\n";
    }
}


// Изображение товара
function getProductImages($articleList, $thumbs = 0, $savePath = 'images/') {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    // Параметры запроса
    $params = [
        'article' => implode(',', $articleList),
        'thumbs' => $thumbs // Установите 1 для миниатюр, 0 для полноразмерных изображений
    ];

    // Выполнение запроса к API
    $response = apiRequest("images", $params);

    if ($response && is_array($response)) {
        foreach ($articleList as $article) {
            echo "Изображения для артикула " . $article . ":\n";

            // Проверяем, есть ли изображения для данного артикула
            if (isset($response[$article]) && is_array($response[$article])) {
                foreach ($response[$article] as $imageUrl) {
                    // Определяем имя файла на основе URL
                    $imageName = basename($imageUrl);
                    $saveFilePath = $savePath . $imageName;

                    // Проверяем, существует ли файл
                    if (file_exists($saveFilePath)) {
                        echo "Файл уже существует: " . $saveFilePath . "\n";
                        continue; // Пропускаем сохранение, если файл уже существует
                    }

                    // Загружаем изображение
                    $imageContent = file_get_contents($imageUrl);
                    if ($imageContent === FALSE) {
                        echo "Ошибка при загрузке изображения: " . $imageUrl . "\n";
                        continue;
                    }

                    // Создаем директорию, если не существует
                    if (!is_dir($savePath)) {
                        mkdir($savePath, 0755, true);
                    }

                    // Сохраняем изображение на сервере
                    $saveResult = file_put_contents($saveFilePath, $imageContent);
                    if ($saveResult === FALSE) {
                        echo "Ошибка при сохранении изображения: " . $imageUrl . "\n";
                    } else {
                        echo "Изображение сохранено: " . $saveFilePath . "\n";
                    }
                }
            } else {
                echo "Нет изображений для артикула " . $article . "\n";
            }
            echo "---------------------------------\n";
        }
    } else {
        echo "Ошибка получения изображений товара\n";
    }
}

// Остатки
function getProductQuantities($articleList) {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    // Ограничение на количество артикулов в одном запросе
    $maxArticlesPerRequest = 1000;
    $articleChunks = array_chunk($articleList, $maxArticlesPerRequest);

    foreach ($articleChunks as $articles) {
        // Запрос остатков товаров
        $response = apiRequest("quantity", ["article" => implode(',', $articles)]);

        if ($response) {
            foreach ($response as $article => $quantity) {
                echo "Остатки для артикула " . $article . ": " . $quantity . "\n";
            }
        } else {
            echo "Ошибка получения остатков товаров для артикула(ов): " . implode(',', $articles) . "\n";
        }
    }
}

// Остатки и цены
function getProductQuantitiesAndPrices($articleList, $excludeMissing = false, $brand = null) {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    // Формирование параметров запроса
    $params = [
        "article" => implode(',', $articleList),
        "exclude_missing" => $excludeMissing ? 'true' : 'false'
    ];
    if ($brand) {
        $params["brand"] = $brand;
    }

    // Запрос остатков товаров и цен
    $response = apiRequest("quantity-price", $params);
    if ($response) {
        foreach ($response as $article => $info) {
            echo "Информация для артикула " . $article . ":\n";
            echo "Остаток: " . $info["quantity"] . "\n";
            echo "Цена дилерская: " . $info["price1"] . "\n";
            echo "Цена розничная: " . $info["price2"] . "\n";
            echo "Цена со скидкой: " . $info["discountPrice"] . "\n";
            echo "Процент скидки: " . $info["discount"] . "\n";
            echo "Склад: " . $info["warehouse"] . "\n";
            echo "\n"; // Для лучшей читаемости между записями
        }
    } else {
        echo "Ошибка получения остатков и цен товаров\n";
    }
}

// Свойства
function getProductProperties($articleList, $categoryList = []) {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    // Формирование параметров запроса
    $params = ["article" => implode(',', $articleList)];
    if (!empty($categoryList)) {
        $params["category"] = implode(',', $categoryList);
    }

    // Выполнение запроса к API
    $response = apiRequest("properties", $params);
    if ($response && isset($response["elements"])) {
        foreach ($response["elements"] as $element) {
            echo "Свойства для артикула " . $element["article"] . ":\n";
            foreach ($element["properties"] as $property) {
                echo $property["name"] . ": " . $property["value"] . "\n";
            }
            echo "\n"; // Для лучшей читаемости между записями
        }
    } else {
        echo "Ошибка получения свойств товаров\n";
    }
}


// Примеры использования
checkDataValidity();
syncCategories(1); // Замените на ID инфоблока категорий
syncBrands(2); // Замените на ID инфоблока брендов
syncProducts(3); // Замените на ID инфоблока товаров
syncProductsWithPagination(3); // Замените на ID инфоблока товаров
getProductQuantities(["A001", "A002"]); // Замените на реальные артикулы
getProductQuantitiesAndPrices(["A001", "A002"], true, "brand-guid"); // Замените на реальные артикулы и GUID бренда
getProductProperties(["A001", "A002"], ["cat1", "cat2"]); // Замените на реальные артикулы и ID категорий
getProductInfo(["A001", "A002"]); // Замените на реальные артикулы
getProductImages(["A001", "A002"]); // Замените на реальные артикулы

?>
