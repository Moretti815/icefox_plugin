<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$stat = \Widget\Stat::alloc();
$posts = \Widget\Contents\Post\Admin::alloc();
$isAllPosts = ('on' == $request->get('__typecho_all_posts') || 'on' == \Typecho\Cookie::get('__typecho_all_posts'));
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <div class="clearfix">
                    <ul class="typecho-option-tabs right">
                        <?php if ($user->pass('editor', true) && !isset($request->uid)): ?>
                            <li class="<?php if ($isAllPosts): ?> current<?php endif; ?>"><a
                                    href="<?php echo $request->makeUriByRequest('__typecho_all_posts=on&page=1'); ?>"><?php _e('所有'); ?></a>
                            </li>
                            <li class="<?php if (!$isAllPosts): ?> current<?php endif; ?>"><a
                                    href="<?php echo $request->makeUriByRequest('__typecho_all_posts=off&page=1'); ?>"><?php _e('我的'); ?></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <ul class="typecho-option-tabs">
                        <li<?php if (!isset($request->status) || 'all' == $request->get('status')): ?> class="current"<?php endif; ?>>
                            <a href="<?php $options->adminUrl('manage-posts.php'
                                . (isset($request->uid) ? '?uid=' . $request->filter('encode')->uid : '')); ?>"><?php _e('可用'); ?></a>
                        </li>
                        <li<?php if ('waiting' == $request->get('status')): ?> class="current"<?php endif; ?>><a
                                href="<?php $options->adminUrl('manage-posts.php?status=waiting'
                                    . (isset($request->uid) ? '&uid=' . $request->filter('encode')->uid : '')); ?>"><?php _e('待审核'); ?>
                                <?php if (!$isAllPosts && $stat->myWaitingPostsNum > 0 && !isset($request->uid)): ?>
                                    <span class="balloon"><?php $stat->myWaitingPostsNum(); ?></span>
                                <?php elseif ($isAllPosts && $stat->waitingPostsNum > 0 && !isset($request->uid)): ?>
                                    <span class="balloon"><?php $stat->waitingPostsNum(); ?></span>
                                <?php elseif (isset($request->uid) && $stat->currentWaitingPostsNum > 0): ?>
                                    <span class="balloon"><?php $stat->currentWaitingPostsNum(); ?></span>
                                <?php endif; ?>
                            </a></li>
                        <li<?php if ('draft' == $request->get('status')): ?> class="current"<?php endif; ?>><a
                                href="<?php $options->adminUrl('manage-posts.php?status=draft'
                                    . (isset($request->uid) ? '&uid=' . $request->filter('encode')->uid : '')); ?>"><?php _e('草稿'); ?>
                                <?php if (!$isAllPosts && $stat->myDraftPostsNum > 0 && !isset($request->uid)): ?>
                                    <span class="balloon"><?php $stat->myDraftPostsNum(); ?></span>
                                <?php elseif ($isAllPosts && $stat->draftPostsNum > 0 && !isset($request->uid)): ?>
                                    <span class="balloon"><?php $stat->draftPostsNum(); ?></span>
                                <?php elseif (isset($request->uid) && $stat->currentDraftPostsNum > 0): ?>
                                    <span class="balloon"><?php $stat->currentDraftPostsNum(); ?></span>
                                <?php endif; ?>
                            </a></li>
                    </ul>
                </div>

                <div class="typecho-list-operate clearfix">
                    <form method="get">
                        <div class="operate">
                            <label><i class="sr-only"><?php _e('全选'); ?></i><input type="checkbox"
                                                                                   class="typecho-table-select-all"/></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i
                                        class="sr-only"><?php _e('操作'); ?></i><?php _e('选中项'); ?> <i
                                        class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li><a lang="<?php _e('你确认要删除这些文章吗?'); ?>"
                                           href="<?php $security->index('/action/contents-post-edit?do=delete'); ?>"><?php _e('删除'); ?></a>
                                    </li>
                                    <?php if ($user->pass('editor', true)): ?>
                                        <li>
                                            <a href="<?php $security->index('/action/contents-post-edit?do=mark&status=publish'); ?>"><?php _e('标记为<strong>%s</strong>', _t('公开')); ?></a>
                                        </li>
                                        <li>
                                            <a href="<?php $security->index('/action/contents-post-edit?do=mark&status=waiting'); ?>"><?php _e('标记为<strong>%s</strong>', _t('待审核')); ?></a>
                                        </li>
                                        <li>
                                            <a href="<?php $security->index('/action/contents-post-edit?do=mark&status=hidden'); ?>"><?php _e('标记为<strong>%s</strong>', _t('隐藏')); ?></a>
                                        </li>
                                        <li>
                                            <a href="<?php $security->index('/action/contents-post-edit?do=mark&status=private'); ?>"><?php _e('标记为<strong>%s</strong>', _t('私密')); ?></a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="search" role="search">
                            <?php if ('' != $request->keywords || '' != $request->category): ?>
                                <a href="<?php $options->adminUrl('manage-posts.php'
                                    . (isset($request->status) || isset($request->uid) ? '?' .
                                        (isset($request->status) ? 'status=' . $request->filter('encode')->status : '') .
                                        (isset($request->uid) ? (isset($request->status) ? '&' : '') . 'uid=' . $request->filter('encode')->uid : '') : '')); ?>"><?php _e('&laquo; 取消筛选'); ?></a>
                            <?php endif; ?>
                            <input type="text" class="text-s" placeholder="<?php _e('请输入关键字'); ?>"
                                   value="<?php echo $request->filter('html')->keywords; ?>" name="keywords"/>
                            <select name="category">
                                <option value=""><?php _e('所有分类'); ?></option>
                                <?php \Widget\Metas\Category\Rows::alloc()->to($category); ?>
                                <?php while ($category->next()): ?>
                                    <option
                                        value="<?php $category->mid(); ?>"<?php if ($request->get('category') == $category->mid): ?> selected="true"<?php endif; ?>><?php $category->name(); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" class="btn btn-s"><?php _e('筛选'); ?></button>
                            <?php if (isset($request->uid)): ?>
                                <input type="hidden" value="<?php echo $request->filter('html')->uid; ?>"
                                       name="uid"/>
                            <?php endif; ?>
                            <?php if (isset($request->status)): ?>
                                <input type="hidden" value="<?php echo $request->filter('html')->status; ?>"
                                       name="status"/>
                            <?php endif; ?>
                        </div>
                    </form>
                </div><!-- end .typecho-list-operate -->

                <form method="post" name="manage_posts" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="20" class="kit-hidden-mb"/>
                                <col width="6%" class="kit-hidden-mb"/>
                                <col width="45%"/>
                                <col width="" class="kit-hidden-mb"/>
                                <col width="18%" class="kit-hidden-mb"/>
                                <col width="16%"/>
                            </colgroup>
                            <thead>
                            <tr>
                                <th class="kit-hidden-mb"></th>
                                <th class="kit-hidden-mb"></th>
                                <th><?php _e('标题'); ?></th>
                                <th class="kit-hidden-mb"><?php _e('作者'); ?></th>
                                <th class="kit-hidden-mb"><?php _e('分类'); ?></th>
                                <th><?php _e('日期'); ?></th>
                                <th><?php _e('操作'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($posts->have()): ?>
                                <?php while ($posts->next()): ?>
                                    <tr id="<?php $posts->theId(); ?>">
                                        <td class="kit-hidden-mb"><input type="checkbox" value="<?php $posts->cid(); ?>"
                                                                         name="cid[]"/></td>
                                        <td class="kit-hidden-mb"><a
                                                href="<?php $options->adminUrl('manage-comments.php?cid=' . ($posts->parentId ? $posts->parentId : $posts->cid)); ?>"
                                                class="balloon-button size-<?php echo \Typecho\Common::splitByCount($posts->commentsNum, 1, 10, 20, 50, 100); ?>"
                                                title="<?php $posts->commentsNum(); ?> <?php _e('评论'); ?>"><?php $posts->commentsNum(); ?></a>
                                        </td>
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
                                            <a href="<?php $options->adminUrl('write-post.php?cid=' . $posts->cid); ?>"
                                               title="<?php _e('编辑 %s', htmlspecialchars($posts->title)); ?>"><i
                                                    class="i-edit"></i></a>
                                            <?php if ('post_draft' != $posts->type): ?>
                                                <a href="<?php $posts->permalink(); ?>"
                                                   title="<?php _e('浏览 %s', htmlspecialchars($posts->title)); ?>"><i
                                                        class="i-exlink"></i></a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="kit-hidden-mb"><a
                                                href="<?php $options->adminUrl('manage-posts.php?__typecho_all_posts=off&uid=' . $posts->author->uid); ?>"><?php $posts->author(); ?></a>
                                        </td>
                                        <td class="kit-hidden-mb"><?php $categories = $posts->categories;
                                            $length = count($categories); ?>
                                            <?php foreach ($categories as $key => $val): ?>
                                                <?php echo '<a href="';
                                                $options->adminUrl('manage-posts.php?category=' . $val['mid']
                                                    . (isset($request->uid) ? '&uid=' . $request->filter('encode')->uid : '')
                                                    . (isset($request->status) ? '&status=' . $request->filter('encode')->status : ''));
                                                echo '">' . $val['name'] . '</a>' . ($key < $length - 1 ? ', ' : ''); ?>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <?php if ($posts->hasSaved): ?>
                                                <span class="description">
                                <?php $modifyDate = new \Typecho\Date($posts->modified); ?>
                                <?php _e('保存于 %s', $modifyDate->word()); ?>
                                </span>
                                            <?php else: ?>
                                                <?php $posts->dateWord(); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                // 查询文章是否已置顶和点赞数
                                                $resultData = $db->fetchRow($db->select('*')->from('table.icefox_archive')->where('cid = ?', $posts->cid));
												$isTop = false;
												$topStyle = '';
												$likesCount = 0;
												if(!empty($resultData)){
													$isTop = $resultData['is_top'];
													$likesCount = intval($resultData['likes']);
												}
												if($isTop){
													$topStyle = 'font-size:12px;color:#FFF;background-color:red;padding:1px 3px;';
												}
                                                ?>
												<a href="<?php $security->index('/action/icefox?do=top&cid='.$posts->cid.'&stat='.$isTop); ?>" style="white-space: nowrap; <?php echo $topStyle;?>"><?php echo $isTop?'取消置顶':'置顶';?></a>
												<span class="show-likes-btn" data-cid="<?php echo $posts->cid; ?>" onclick="showLikesModal(<?php echo $posts->cid; ?>, '<?php echo addslashes(htmlspecialchars($posts->title)); ?>')" style="white-space: nowrap; margin-left:8px; cursor:pointer; color:#467b96;">点赞(<?php echo $likesCount; ?>)</span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"><h6 class="typecho-list-table-title"><?php _e('没有任何文章'); ?></h6>
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
                            <label><i class="sr-only"><?php _e('全选'); ?></i><input type="checkbox"
                                                                                   class="typecho-table-select-all"/></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i
                                        class="sr-only"><?php _e('操作'); ?></i><?php _e('选中项'); ?> <i
                                        class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li><a lang="<?php _e('你确认要删除这些文章吗?'); ?>"
                                           href="<?php $security->index('/action/contents-post-edit?do=delete'); ?>"><?php _e('删除'); ?></a>
                                    </li>
                                    <?php if ($user->pass('editor', true)): ?>
                                        <li>
                                            <a href="<?php $security->index('/action/contents-post-edit?do=mark&status=publish'); ?>"><?php _e('标记为<strong>%s</strong>', _t('公开')); ?></a>
                                        </li>
                                        <li>
                                            <a href="<?php $security->index('/action/contents-post-edit?do=mark&status=waiting'); ?>"><?php _e('标记为<strong>%s</strong>', _t('待审核')); ?></a>
                                        </li>
                                        <li>
                                            <a href="<?php $security->index('/action/contents-post-edit?do=mark&status=hidden'); ?>"><?php _e('标记为<strong>%s</strong>', _t('隐藏')); ?></a>
                                        </li>
                                        <li>
                                            <a href="<?php $security->index('/action/contents-post-edit?do=mark&status=private'); ?>"><?php _e('标记为<strong>%s</strong>', _t('私密')); ?></a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>

                        <?php if ($posts->have()): ?>
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
include 'copyright.php';
include 'common-js.php';
include 'table-js.php';
?>

<!-- 点赞记录弹窗 -->
<div id="likes-modal" class="typecho-popup" style="display:none;">
    <div class="typecho-popup-bg" onclick="closeLikesModal()"></div>
    <div class="typecho-popup-content" style="width:720px;max-width:90%;max-height:80vh;overflow:auto;">
        <div class="typecho-popup-head">
            <h3>文章《<span id="likes-modal-title"></span>》的点赞记录</h3>
            <button class="typecho-popup-close" onclick="closeLikesModal()">&times;</button>
        </div>
        <div class="typecho-popup-body" id="likes-modal-body">
            <div class="loading" style="text-align:center;padding:40px;">加载中...</div>
        </div>
        <div class="typecho-popup-foot" id="likes-modal-foot" style="display:none;">
            <span>共 <strong id="likes-total">0</strong> 条记录</span>
            <ul class="typecho-pager" id="likes-pager"></ul>
        </div>
    </div>
</div>

<style>
.typecho-popup {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.typecho-popup-bg {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
}
.typecho-popup-content {
    position: relative;
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.typecho-popup-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e9e9e9;
}
.typecho-popup-head h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}
.typecho-popup-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    line-height: 1;
    padding: 0;
    width: 30px;
    height: 30px;
}
.typecho-popup-close:hover {
    color: #333;
}
.typecho-popup-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}
.typecho-popup-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-top: 1px solid #e9e9e9;
    background: #f9f9f9;
}
.likes-table {
    width: 100%;
    border-collapse: collapse;
}
.likes-table th,
.likes-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #e9e9e9;
}
.likes-table th {
    background: #f5f5f5;
    font-weight: 600;
    font-size: 13px;
    color: #666;
}
.likes-table td {
    font-size: 13px;
}
.likes-table tr:hover td {
    background: #fafafa;
}
.likes-delete-btn {
    color: #c33;
    cursor: pointer;
    background: none;
    border: none;
    font-size: 13px;
}
.likes-delete-btn:hover {
    color: #a00;
    text-decoration: underline;
}
.likes-delete-btn:disabled {
    color: #999;
    cursor: not-allowed;
}
.likes-empty {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}
#likes-pager {
    display: flex;
    gap: 5px;
    margin: 0;
    padding: 0;
    list-style: none;
}
#likes-pager li a,
#likes-pager li span {
    display: inline-block;
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
    text-decoration: none;
    color: #333;
    font-size: 12px;
}
#likes-pager li a:hover {
    background: #f5f5f5;
}
#likes-pager li.current span {
    background: #467b96;
    color: #fff;
    border-color: #467b96;
}
</style>

<script>
(function() {
    var currentCid = 0;
    var currentPage = 1;
    var pageSize = 10;
    var apiBase = '<?php echo rtrim($options->index, '/'); ?>/';

    // 显示点赞记录弹窗
    window.showLikesModal = function(cid, title) {
        currentCid = cid;
        currentPage = 1;
        document.getElementById('likes-modal-title').textContent = title;
        document.getElementById('likes-modal').style.display = 'flex';
        document.getElementById('likes-modal-foot').style.display = 'none';
        loadLikesData();
    };

    // 关闭弹窗
    window.closeLikesModal = function() {
        document.getElementById('likes-modal').style.display = 'none';
    };

    // 加载点赞数据
    function loadLikesData() {
        var body = document.getElementById('likes-modal-body');
        body.innerHTML = '<div class="loading" style="text-align:center;padding:40px;">加载中...</div>';

        var url = apiBase + 'action/icefox?do=getLikeRecords&cid=' + currentCid + '&page=' + currentPage + '&pageSize=' + pageSize;

        fetch(url)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    renderLikesTable(data);
                } else {
                    body.innerHTML = '<div class="likes-empty">' + (data.message || '加载失败') + '</div>';
                }
            })
            .catch(function(err) {
                body.innerHTML = '<div class="likes-empty">网络错误，请重试</div>';
            });
    }

    // 渲染表格
    function renderLikesTable(data) {
        var body = document.getElementById('likes-modal-body');
        var foot = document.getElementById('likes-modal-foot');

        if (data.data.length === 0) {
            body.innerHTML = '<div class="likes-empty">暂无点赞记录</div>';
            foot.style.display = 'none';
            return;
        }

        var html = '<table class="likes-table">';
        html += '<thead><tr><th>昵称</th><th>邮箱</th><th>IP</th><th>点赞时间</th><th>操作</th></tr></thead>';
        html += '<tbody>';

        data.data.forEach(function(item) {
            html += '<tr data-id="' + item.id + '">';
            html += '<td>' + escapeHtml(item.author) + '</td>';
            html += '<td>' + escapeHtml(item.mail) + '</td>';
            html += '<td>' + escapeHtml(item.ip) + '</td>';
            html += '<td>' + escapeHtml(item.created_at) + '</td>';
            html += '<td><button class="likes-delete-btn" onclick="deleteLikeRecord(' + item.id + ', this)">删除</button></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        body.innerHTML = html;

        // 更新分页
        document.getElementById('likes-total').textContent = data.total;
        foot.style.display = 'flex';
        renderPager(data.page, data.totalPages);
    }

    // 渲染分页
    function renderPager(page, totalPages) {
        var pager = document.getElementById('likes-pager');
        var html = '';

        if (page > 1) {
            html += '<li><a href="javascript:;" onclick="goToPage(' + (page - 1) + ')">« 上一页</a></li>';
        }

        html += '<li class="current"><span>' + page + ' / ' + totalPages + '</span></li>';

        if (page < totalPages) {
            html += '<li><a href="javascript:;" onclick="goToPage(' + (page + 1) + ')">下一页 »</a></li>';
        }

        pager.innerHTML = html;
    }

    // 跳转页面
    window.goToPage = function(page) {
        currentPage = page;
        loadLikesData();
    };

    // 删除点赞记录
    window.deleteLikeRecord = function(id, btn) {
        if (!confirm('确定删除这条点赞记录吗？')) {
            return;
        }

        btn.disabled = true;
        btn.textContent = '删除中...';

        fetch(apiBase + 'action/icefox?do=deleteLikeRecord', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                // 重新加载当前页数据
                loadLikesData();
                // 更新列表中的点赞数
                updateLikesCount(currentCid);
                // 显示成功提示
                showNotice(data.message, 'success');
            } else {
                showNotice(data.message || '删除失败', 'error');
                btn.disabled = false;
                btn.textContent = '删除';
            }
        })
        .catch(function(err) {
            showNotice('网络错误，请重试', 'error');
            btn.disabled = false;
            btn.textContent = '删除';
        });
    };

    // 更新列表中的点赞数显示
    function updateLikesCount(cid) {
        var btns = document.querySelectorAll('.show-likes-btn[data-cid="' + cid + '"]');
        btns.forEach(function(btn) {
            var match = btn.textContent.match(/点赞\((\d+)\)/);
            if (match) {
                var count = parseInt(match[1]) - 1;
                if (count < 0) count = 0;
                btn.textContent = '点赞(' + count + ')';
            }
        });
    }

    // HTML 转义
    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // 显示提示
    function showNotice(message, type) {
        var notice = document.createElement('div');
        notice.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:10000;padding:12px 24px;border-radius:4px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
        if (type === 'success') {
            notice.style.background = '#d4edda';
            notice.style.color = '#155724';
            notice.style.border = '1px solid #c3e6cb';
        } else {
            notice.style.background = '#f8d7da';
            notice.style.color = '#721c24';
            notice.style.border = '1px solid #f5c6cb';
        }
        notice.textContent = message;
        document.body.appendChild(notice);
        setTimeout(function() {
            notice.style.transition = 'opacity 0.3s';
            notice.style.opacity = '0';
            setTimeout(function() { notice.remove(); }, 300);
        }, 2000);
    }

    // ESC 关闭弹窗
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLikesModal();
        }
    });
})();
</script>

<?php
include 'footer.php';
?>
