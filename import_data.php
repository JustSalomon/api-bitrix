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
    return json_decode($response, true);
}

// Проверка актуальности данных
function checkDataValidity() {
    $response = apiRequest("date");
    if ($response) {
        echo "Актуальность данных: " . ($response["valid"] ? "Данные актуальны" : "Данные устарели") . "\n";
    } else {
        echo "Ошибка проверки актуальности данных\n";
    }
}

// Синхронизация категорий
function syncCategories($iblockId) {
    $categories = apiRequest("categories");
    if ($categories) {
        foreach ($categories as $category) {
            $arFields = [
                "NAME" => $category["name"],
                "IBLOCK_ID" => $iblockId,
                "EXTERNAL_ID" => $category["id"],
                "DEPTH_LEVEL" => $category["level"],
            ];
            $el = new CIBlockSection;
            $res = $el->Add($arFields);
            if ($res) {
                echo "Категория создана/обновлена: " . $category["name"] . "\n";
            } else {
                echo "Ошибка при создании/обновлении категории: " . $el->LAST_ERROR . "\n";
            }
        }
    } else {
        echo "Ошибка получения списка категорий\n";
    }
}

// Синхронизация брендов
function syncBrands($iblockId) {
    $brands = apiRequest("brands");
    if ($brands) {
        foreach ($brands as $brand) {
            $arFields = [
                "NAME" => $brand["name"],
                "IBLOCK_ID" => $iblockId,
                "EXTERNAL_ID" => $brand["id"],
            ];
            $el = new CIBlockElement;
            $res = $el->Add($arFields);
            if ($res) {
                echo "Бренд создан/обновлен: " . $brand["name"] . "\n";
            } else {
                echo "Ошибка при создании/обновлении бренда: " . $el->LAST_ERROR . "\n";
            }
        }
    } else {
        echo "Ошибка получения списка брендов\n";
    }
}

// Получение списка товаров
function syncProducts($iblockId) {
    $elements = apiRequest("elements");
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
                ],
            ];
            $el = new CIBlockElement;
            $existingElementId = CIBlockElement::GetList([], ["IBLOCK_ID" => $iblockId, "XML_ID" => $element["article"]], false, false, ["ID"])->Fetch()["ID"];
            if ($existingElementId) {
                $res = $el->Update($existingElementId, $arFields);
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

// Получение списка товаров с постраничностью
function syncProductsWithPagination($iblockId) {
    $offset = 0;
    $limit = 100;
    do {
        $elements = apiRequest("elements-pagination", ["limit" => $limit, "offset" => $offset]);
        $totalCount = $elements["pagination"]["totalCount"];

        // Создание или обновление товаров в Битрикс
        foreach ($elements["elements"] as $element) {
            $arFields = [
                "NAME" => $element["name"],
                "IBLOCK_ID" => $iblockId,
                "XML_ID" => $element["article"],
                "PROPERTY_VALUES" => [
                    "ARTICLE_PN" => $element["article_pn"],
                    "FULL_NAME" => $element["full_name"],
                    "PRICE" => $element["price1"],
                    "QUANTITY" => $element["quantity"],
                ],
            ];
            $el = new CIBlockElement;
            $existingElementId = CIBlockElement::GetList([], ["IBLOCK_ID" => $iblockId, "XML_ID" => $element["article"]], false, false, ["ID"])->Fetch()["ID"];
            if ($existingElementId) {
                $res = $el->Update($existingElementId, $arFields);
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
    } while ($offset < $totalCount);
}

// Получение информации о товаре
function getProductInfo($articleList) {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    $response = apiRequest("product-info", ["article" => implode(',', $articleList)]);
    if ($response) {
        foreach ($response as $article => $info) {
            echo "Информация о товаре для артикула " . $article . ":\n";
            echo "Название: " . $info["name"] . "\n";
            echo "Описание: " . $info["description"] . "\n";
            echo "Бренд: " . $info["brand"] . "\n";
            echo "Категория: " . $info["category"] . "\n";
            // Добавьте другие поля по необходимости
        }
    } else {
        echo "Ошибка получения информации о товаре\n";
    }
}

// Получение изображения товара
function getProductImages($articleList) {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    $response = apiRequest("product-images", ["article" => implode(',', $articleList)]);
    if ($response) {
        foreach ($response as $article => $images) {
            echo "Изображения для артикула " . $article . ":\n";
            foreach ($images as $image) {
                echo "URL изображения: " . $image["url"] . "\n";
                // Здесь можно сохранить изображение по URL или обработать другим способом
            }
        }
    } else {
        echo "Ошибка получения изображений товара\n";
    }
}

// Получение остатков товаров
function getProductQuantities($articleList) {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    $response = apiRequest("quantity", ["article" => implode(',', $articleList)]);
    if ($response) {
        foreach ($response as $article => $quantity) {
            echo "Остатки для артикула " . $article . ": " . $quantity . "\n";
        }
    } else {
        echo "Ошибка получения остатков товаров\n";
    }
}

// Получение остатков и цен товаров
function getProductQuantitiesAndPrices($articleList, $excludeMissing = false, $brand = null) {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    $params = [
        "article" => implode(',', $articleList),
        "exclude_missing" => $excludeMissing
    ];
    if ($brand) {
        $params["brand"] = $brand;
    }

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
        }
    } else {
        echo "Ошибка получения остатков и цен товаров\n";
    }
}

// Получение свойств товаров
function getProductProperties($articleList, $categoryList = []) {
    if (empty($articleList)) {
        echo "Список артикулов пуст\n";
        return;
    }

    $params = ["article" => implode(',', $articleList)];
    if (!empty($categoryList)) {
        $params["category"] = implode(',', $categoryList);
    }

    $response = apiRequest("properties", $params);
    if ($response) {
        foreach ($response["elements"] as $element) {
            echo "Свойства для артикула " . $element["article"] . ":\n";
            foreach ($element["properties"] as $property) {
                echo $property["name"] . ": " . $property["value"] . "\n";
            }
        }
    } else {
        echo "Ошибка получения свойств товаров\n";
    }
}

// Примеры использования функций
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
