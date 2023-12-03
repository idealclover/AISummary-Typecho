<?php
class AISummary_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $db;
    private $options;
    private $prefix;

    public function generateSummary()
    {
        $cids = $this->request->filter('int')->getArray('cid');
        if ($cids && is_array($cids)) {
            foreach ($cids as $cid) {
                // 取出文章
                $post = $this->db->fetchRow($this->db->select('title', 'text')->from('table.contents')->where('cid = ?', $cid));
                $summary = AISummary_Plugin::callApi($post['title'], $post['text']);

                // 插入或更新数据库
                // https://github.com/typecho/typecho/blob/43c54328f724055173f2b7b1c67755ca3328d923/var/Widget/Base/Contents.php#L380
                $summaryField = Typecho_Widget::widget('Widget_Options')->plugin('AISummary')->field;
                $exist = $this->db->fetchRow($this->db->select('cid')->from('table.fields')->where('cid = ? AND name = ?', $cid, $summaryField));
                $rows = [
                    'cid'         => $cid,
                    'name'        => $summaryField,
                    'type'        => 'str',
                    'str_value'   => $summary,
                    'int_value'   => 0,
                    'float_value' => 0
                ];
                if (empty($exist)) {
                    $rows['cid'] = $cid;
                    $rows['name'] = $summaryField;
                    $this->db->query($this->db->insert('table.fields')->rows($rows));
                } else {
                    $this->db->query($this->db->update('table.fields')->rows($rows)->where('cid = ? AND name = ?', $cid, $summaryField));
                }

                /** 设置高亮 */
                // $this->widget('Widget_Notice')->highlight('post-' . $cid);
            }
        }

        /** 提示信息 */
        $this->widget('Widget_Notice')->set(_t('已生成AI摘要'), NULL, 'success');
        $this->response->goBack();
        // $this->response->redirect(Typecho_Common::url('extending.php?panel=AISummary%2Fmanage-summaries.php', $this->options->adminUrl));
    }

    public function action()
    {
        $this->db = Typecho_Db::get();
        $this->prefix = $this->db->getPrefix();
        $this->options = Typecho_Widget::widget('Widget_Options');
        $this->on($this->request->is('do=generate'))->generateSummary();
        // $this->on($this->request->is('do=addhanny'))->addHannysBlog();
        // $this->on($this->request->is('do=update'))->updateLink();
        // $this->on($this->request->is('do=delete'))->deleteLink();
        // $this->on($this->request->is('do=sort'))->sortLink();
        $this->response->redirect($this->options->adminUrl);
    }
}
