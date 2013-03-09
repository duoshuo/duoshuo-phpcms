
1.访问http://duoshuo.com/create-site/ 创建多说站点，在管理后台获取代码

2.将目录拷贝到phpcms主目录下(api目录会合并到原api目录)

3.访问你站点下的duoshuo安装接口：http://xxxxx.com/api.php?op=duoshuo_install

4.安装完毕，删除api/duoshuo_install.php文件

5.备份模板文件(每种文档类型，已经评论列表模板)

6.在模板文件中插入多说代码(增加data-thread-key和data-title参数)

7.更新文档，在“应用”->“已安装的应用”里找到多说，“配置”。填入name和secret

8.数据库操作(建回流表)
建回流表：

CREATE TABLE IF NOT EXISTS `v9_duoshuo_commentmeta` (  
  `post_id` bigint(20) unsigned NOT NULL COMMENT '多说评论id',  
  `tableid` int(11) unsigned NOT NULL COMMENT '表id号',  
  `cid` int(10) unsigned NOT NULL COMMENT '本地表内评论id',  
  PRIMARY KEY (`post_id`),  
  KEY `tableid` (`tableid`,`cid`)  
) ENGINE=MyISAM;

====！！申请站点后跟小武联系，告知你申请的多说二级域名以及api接口地址,一般是http://xxxxx.com/api.php?op=duoshuo
开通实时回流功能

9.测试回流效果
