<?php

/**
 * AISummary 调用ChatGPT、Kimi Chat等AI接口，智能提取文章摘要
 *
 * @package AISummary
 * @author idealclover
 * @version 1.0.0
 * @link https://idealclover.top
 */
class AISummary_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        //检查是否有curl扩展
        if (!extension_loaded('curl')) {
            throw new Typecho_Plugin_Exception('缺少curl扩展支持.');
        }

        Helper::addPanel(3, 'AISummary/manage-summaries.php', '摘要', '管理AI摘要', 'administrator');
        Helper::addAction('summary-edit', 'AISummary_Action');
        Typecho_Plugin::factory('Widget_Archive')->header = array('AISummary_Plugin', 'header');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('AISummary_Plugin', 'customExcerpt');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('AISummary_Plugin', 'customContent');
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('AISummary_Plugin', 'onFinishPublish');
    }

    public static function deactivate()
    {
        Helper::removePanel(3, 'AISummary/manage-summaries.php');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 添加输入框：模型名
        $modelName = new Typecho_Widget_Helper_Form_Element_Text(
            'modelName',
            NULL,
            'gpt-3.5-turbo-16k',
            _t('模型名'),
            _t('请输入生成摘要使用的模型名，默认为 gpt-3.5-turbo-16k')
        );
        $form->addInput($modelName);

        // 添加输入框：key值
        $keyValue = new Typecho_Widget_Helper_Form_Element_Text(
            'keyValue',
            NULL,
            NULL,
            _t('API KEY'),
            _t('请输入调用 API 的 key')
        );
        $form->addInput($keyValue);

        // 添加输入框：API地址
        $apiUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'apiUrl',
            NULL,
            NULL,
            _t('API 地址'),
            _t('请输入API地址，不要省略（https://）或（http://）不要带有（/v1）')
        );
        $form->addInput($apiUrl);

        // 添加输入框：Prompt
        $prompt = new Typecho_Widget_Helper_Form_Element_Textarea(
            'prompt',
            NULL,
            '你的任务是生成文章的摘要。请你根据以下文章内容生成100字内的摘要，除了你生成的的摘要内容，请不要输出其他任何无关内容。',
            _t('提示词'),
            _t('请输入用于生成摘要的 prompt')
        );
        $form->addInput($prompt);

        // 添加输入框：摘要最大长度
        $maxLength = new Typecho_Widget_Helper_Form_Element_Text(
            'maxLength',
            NULL,
            '150',
            _t('摘要最大长度'),
            _t('请输入摘要的最大文字长度，由于prompt并不能很好限制字数，建议比prompt中规定字数更大')
        );
        $form->addInput($maxLength);

        $replaceSummary = new Typecho_Widget_Helper_Form_Element_Radio(
            'replaceSummary',
            array('0' => '不替换', '1' => '替换'),
            '1',
            '是否替换默认摘要',
            '若选择替换,系统将会替换摘要为AI生成，未生成AI摘要文章不受影响。'
        );
        $form->addInput($replaceSummary);

        $summaryOnFinish = new Typecho_Widget_Helper_Form_Element_Radio(
            'summaryOnFinish',
            array('0' => '不启用', '1' => '启用'),
            '1',
            '文章修改时更新摘要',
            '在文章发布或修改时自动进行摘要更新，可能会使得发布速度变慢，请耐心等待'
        );
        $form->addInput($summaryOnFinish);

        $summaryStyle = new Typecho_Widget_Helper_Form_Element_Radio(
            'summaryStyle',
            array('0' => '不显示', '1' => '使用默认引用样式', '2' => '使用自定义样式'),
            '1',
            '正文摘要显示样式',
            '选择在正文开头以何种样式显示摘要，使用主题内的引言样式或进行自定义样式设置'
        );
        $form->addInput($summaryStyle);

        // 添加输入框，自定义样式
        $css = new Typecho_Widget_Helper_Form_Element_Textarea(
            'css',
            NULL,
            "<style>\n.aisummary{\n}\n</style>",
            _t('自定义样式'),
            _t('在这里输入额外的自定义样式，加载到head标签中控制AI摘要的表现样式<br />摘要 class="aisummary"，需包含style标签<br />如无定制需求此项可留空')
        );
        $form->addInput($css);

        // 添加输入框：摘要前缀
        $prefix = new Typecho_Widget_Helper_Form_Element_Text(
            'prefix',
            NULL,
            "<strong>AI摘要：</strong>{{text}}<br /><br />Powered by <a href='https://idealclover.top/archives/636/'>AISummary</a>.",
            _t('正文摘要前后固定文字'),
            _t('请输入在正文中出现的摘要展示前后固定文字，正文用{{text}}代替，仅在正文摘要显示时生效')
        );
        $form->addInput($prefix);

        // 添加字段设置：自定义存储字段
        $field = new Typecho_Widget_Helper_Form_Element_Text(
            'field',
            NULL,
            'summary',
            _t('存储字段名称'),
            _t('存储在数据库中的摘要字段名称，默认为"summary"')
        );
        $form->addInput($field);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    public static function customExcerpt($excerpt, $widget)
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('AISummary');
        // 不启用的话直接 return
        if ($options->replaceSummary === "0") return $excerpt;
        // 获取用户自定义的字段
        $summaryField = Typecho_Widget::widget('Widget_Options')->plugin('AISummary')->field;
        $customSummary = $widget->fields->$summaryField;
        $maxLength = Typecho_Widget::widget('Widget_Options')->plugin('AISummary')->maxLength;

        if (!empty($customSummary)) {
            $excerpt = $customSummary;

            if (mb_strlen($excerpt) > $maxLength) {
                $excerpt = mb_substr($excerpt, 0, $maxLength) . '...';
            }
        }

        return $excerpt;
    }

    public static function customContent($content, $widget)
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('AISummary');
        // 不启用的话直接 return
        if ($options->summaryStyle === '0') return $content;
        // 获取用户自定义的字段
        $summaryField = $options->field;
        $customSummary = $widget->fields->$summaryField;
        if (empty($customSummary)) return $content;
        $pureSummary = str_replace("{{text}}", $customSummary, $options->prefix);
        if ($options->summaryStyle === '1') {
            $summaryString = '<blockquote class="aisummary">' . $pureSummary . "</blockquote>";
            $content = $summaryString . $content;
        } else if ($options->summaryStyle === '2') {
            $summaryString = '<div class="aisummary">' . $pureSummary . "</div>";
            $content = $summaryString . $content;
        }

        // 废弃方案：使用 markdown 添加文本
        // $markdownTag = "<!--markdown-->";
        // Check if the content starts with the markdown tag
        // if (strpos($content, $markdownTag) === 0) {
        // Create the summary string
        // $summaryString = "> " . $customSummary . "\n";
        // $content = substr_replace($content, $summaryString, strlen($markdownTag), 0);
        // }

        return $content;
    }

    public static function onFinishPublish($contents, $obj)
    {
        // 获取用户自定义的字段
        $options = Typecho_Widget::widget('Widget_Options')->plugin('AISummary');
        // 不启用的话直接 return
        if ($options->summaryOnFinish === "0") return $contents;
        $summaryField = $options->field;

        $db = Typecho_Db::get();
        // 检查 'summary' 字段是否存在并获取其值
        $rows = $db->fetchRow($db->select('str_value')->from('table.fields')->where('cid = ?', $obj->cid)->where('name = ?', $summaryField));

        // 如果 'summary' 字段不存在或其值为空，则使用 callApi 生成内容
        if (!$rows || empty($rows['str_value'])) {
            $title = $contents['title'];
            $text = $contents['text'];
            $apiResponse = self::callApi($title, $text);

            // 保存到自定义字段
            if ($rows) {
                $db->query($db->update('table.fields')->rows(array('str_value' => $apiResponse))->where('cid = ?', $obj->cid)->where('name = ?', $summaryField));
            } else {
                $db->query($db->insert('table.fields')->rows(array('cid' => $obj->cid, 'name' => $summaryField, 'type' => 'str', 'str_value' => $apiResponse, 'int_value' => 0, 'float_value' => 0)));
            }
        }
        return $contents;
    }

    public static function callApi($title, $text)
    {
        // 获取用户填入的值
        $options = Typecho_Widget::widget('Widget_Options')->plugin('AISummary');
        $modelName = $options->modelName;
        $keyValue = $options->keyValue;
        $apiUrl = rtrim($options->apiUrl, '/') . '/v1/chat/completions';
        $prompt = $options->prompt;
        $maxLength = $options->maxLength;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $keyValue
        ];

        $title = addslashes($title);
        $text = addslashes($text);

        $data = array(
            "model" => $modelName,
            "messages" => array(
                array(
                    "role" => "system",
                    "content" => $prompt
                ),
                array(
                    "role" => "user",
                    "content" => $title . $text
                )
            ),
            "temperature" => 0
        );

        $maxRetries = 5;
        $retries = 0;
        $waitTime = 2;

        while ($retries < $maxRetries) {
            try {
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    throw new Exception(curl_error($ch), curl_errno($ch));
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);

                if ($httpCode == 200) {
                    $decodedResponse = json_decode($response, true);
                    return trim($decodedResponse['choices'][0]['message']['content']);
                }

                throw new Exception("HTTP status code: " . $httpCode);
            } catch (Exception $e) {
                $retries++;
                sleep($waitTime);
                $waitTime *= 2;
            }
        }

        return "";
    }

    /**
     * 相关css加载在头部
     */
    public static function header()
    {
        $css = Typecho_Widget::widget('Widget_Options')->plugin('AISummary')->css;
        // echo ('<style></style>');
        if (!empty($css))
            echo $css;
    }
}
