# 汉化辅助工具-语音台词对照器

汉化时经常遇到某句话不确定语气的情况，所以此物便诞生了，用于根据台词搜索对应的语音，确认这句话的语气。

使用方法很简单

1.在数据库中创建用于储存对照关系的表

![](https://raw.githubusercontent.com/icey9527/VoiceLineMatcher/refs/heads/main/image/0.png)

2.导入对照数据，并将数据库配置到php脚本中

![](https://raw.githubusercontent.com/icey9527/VoiceLineMatcher/refs/heads/main/image/1.png)

3.将语音文件配置到php脚本中，我用的是相对路径，也就是在与php脚本同目录下的子文件夹中，然后在语音目录那里填写子文件夹名就可以了，应该也可以资源分离。

点击文本即可播放对应语音，使用搜索功能搜索到文本后长按台词，可以转到该台词所在的页数。

![](https://raw.githubusercontent.com/icey9527/VoiceLineMatcher/refs/heads/main/image/2.png)

[可以体验下我的](http://scn.wbnb.top)
