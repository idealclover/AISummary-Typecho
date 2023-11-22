<?php
// include 'common.php';
include 'header.php';
include 'menu.php';

// $stat = \Widget\Stat::alloc();
// $posts = \Widget\Contents\Post\Admin::alloc('pageSize=10');
// $isAllPosts = ('on' == $request->get('__typecho_all_posts') || 'on' == \Typecho\Cookie::get('__typecho_all_posts'));

$stat = Typecho_Widget::widget('Widget_Stat');
$posts = Typecho_Widget::widget('Widget_Contents_Post_Admin', 'pageSize=10');
$isAllPosts = ('on' == $request->get('__typecho_all_posts') || 'on' == Typecho_Cookie::get('__typecho_all_posts'));
?>
<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <div class="typecho-list-operate clearfix">
                    <form method="get">
                        <div class="operate">
                            <label><i class="sr-only"><?php _e('全选'); ?></i><input type="checkbox" class="typecho-table-select-all" /></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i class="sr-only"><?php _e('操作'); ?></i><?php _e('选中项'); ?> <i class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a href="<?php $options->index('/action/summary-edit?do=generate'); ?>"><?php _e(_t('生成摘要')); ?></a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="search" role="search">
                            <?php if ('' != $request->keywords || '' != $request->category) : ?>
                                <a href="<?php $options->adminUrl('extending.php?panel=AISummary%2Fmanage-summaries.php'
                                                . (isset($request->status) || isset($request->uid) ? '?' .
                                                    (isset($request->status) ? 'status=' . $request->filter('encode')->status : '') .
                                                    (isset($request->uid) ? (isset($request->status) ? '&' : '') . 'uid=' . $request->filter('encode')->uid : '') : '')); ?>"><?php _e('&laquo; 取消筛选'); ?></a>
                            <?php endif; ?>
                            <input type="text" class="text-s" placeholder="<?php _e('请输入关键字'); ?>" value="<?php echo $request->filter('html')->keywords; ?>" name="keywords" />
                            <select name="category">
                                <option value=""><?php _e('所有分类'); ?></option>
                                <?php Typecho_Widget::widget('Widget_Metas_Category_List')->to($category); ?>
                                <?php // \Widget\Metas\Category\Rows::alloc()->to($category);
                                ?>
                                <?php while ($category->next()) : ?>
                                    <option value="<?php $category->mid(); ?>" <?php if ($request->get('category') == $category->mid) : ?> selected="true" <?php endif; ?>><?php $category->name(); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <input type="hidden" name="panel" value="AISummary/manage-summaries.php">
                            <button type="submit" class="btn btn-s"><?php _e('筛选'); ?></button>
                            <?php if (isset($request->uid)) : ?>
                                <input type="hidden" value="<?php echo $request->filter('html')->uid; ?>" name="uid" />
                            <?php endif; ?>
                            <?php if (isset($request->status)) : ?>
                                <input type="hidden" value="<?php echo $request->filter('html')->status; ?>" name="status" />
                            <?php endif; ?>
                        </div>
                    </form>
                </div><!-- end .typecho-list-operate -->

                <form method="post" name="manage_posts" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="20" class="kit-hidden-mb" />
                                <col width="40%" />
                                <col width="" />
                                <col width="6%" class="kit-hidden-mb" />
                                <col width="6%" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="kit-hidden-mb"></th>
                                    <th><?php _e('标题'); ?></th>
                                    <th><?php _e('摘要'); ?></th>
                                    <th><?php _e('字数'); ?></th>
                                    <th><?php _e('操作'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($posts->have()) : ?>
                                    <?php while ($posts->next()) : ?>
                                        <tr id="<?php $posts->theId(); ?>">
                                            <td class="kit-hidden-mb"><input type="checkbox" value="<?php $posts->cid(); ?>" name="cid[]" /></td>
                                            <td>
                                                <a href="<?php $options->adminUrl('write-post.php?cid=' . $posts->cid); ?>"><?php $posts->title(); ?></a>
                                                <?php
                                                if ($posts->hasSaved || 'post_draft' == $posts->type) {
                                                    echo '<em class="status">' . _t('草稿') . '</em>';
                                                }
                                                if ('hidden' == $posts->status) {
                                                    echo '<em class="status">' . _t('隐藏') . '</em>';
                                                } elseif ('waiting' == $posts->status) {
                                                    echo '<em class="status">' . _t('待审核') . '</em>';
                                                } elseif ('private' == $posts->status) {
                                                    echo '<em class="status">' . _t('私密') . '</em>';
                                                } elseif ($posts->password) {
                                                    echo '<em class="status">' . _t('密码保护') . '</em>';
                                                }
                                                ?>
                                                <a href="<?php $options->adminUrl('write-post.php?cid=' . $posts->cid); ?>" title="<?php _e('编辑 %s', htmlspecialchars($posts->title)); ?>"><i class="i-edit"></i></a>
                                                <?php if ('post_draft' != $posts->type) : ?>
                                                    <a href="<?php $posts->permalink(); ?>" title="<?php _e('浏览 %s', htmlspecialchars($posts->title)); ?>"><i class="i-exlink"></i></a>
                                                <?php endif; ?>
                                            </td>
                                            <?php $db = Typecho_Db::get();
                                            // 获取用户自定义的字段
                                            $summaryField = Typecho_Widget::widget('Widget_Options')->plugin('AISummary')->field;
                                            $rows = $db->fetchAll($db->select()->from('table.fields')->where('cid = ?', $posts->cid));
                                            $flag = false;
                                            foreach ($rows as $row) {
                                                if ($row['name'] == $summaryField) {
                                                    echo '<td>' . $row['str_value'] . '</td><td>' . mb_strlen($row['str_value'], 'UTF-8') . '</td>';
                                                    $flag = true;
                                                    break;
                                                }
                                            }
                                            if (!$flag) echo '<td></td><td></td>';
                                            ?>
                                            <td><a href="<?php $options->index('/action/summary-edit?do=generate&cid=' . $posts->cid); ?>"><?php _e(_t('生成')); ?></a></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="6">
                                            <h6 class="typecho-list-table-title"><?php _e('没有任何文章'); ?></h6>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form><!-- end .operate-form -->

                <div class="typecho-list-operate clearfix">
                    <form method="get">
                        <div class="operate">
                            <label><i class="sr-only"><?php _e('全选'); ?></i><input type="checkbox" class="typecho-table-select-all" /></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i class="sr-only"><?php _e('操作'); ?></i><?php _e('选中项'); ?> <i class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a href="<?php $options->index('/action/summary-edit?do=generate'); ?>"><?php _e(_t('生成摘要')); ?></a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <?php if ($posts->have()) : ?>
                            <ul class="typecho-pager">
                                <?php $posts->pageNav(); ?>
                            </ul>
                        <?php endif; ?>
                    </form>
                </div><!-- end .typecho-list-operate -->
            </div><!-- end .typecho-list -->
        </div><!-- end .typecho-page-main -->
    </div>
</div>

<?php
// include 'copyright.php';
include 'common-js.php';
include 'table-js.php';
// include 'footer.php';
?>