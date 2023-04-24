## 用helm安装
1. 创建pv，pvc，rbac一套

	```
	kubectl create -f pv.yaml
	kubectl create -f provsioner.yaml
	```


2. 添加阿里云的源

	```
	
	helm repo add https://kubernetes.oss-cn-hangzhou.aliyuncs.com/charts
	
	helm repo update
	
	```

3. 下载压缩包
	```
	helm pull ali/jenkins
	```

4. 修改values.yaml

	```
	1. 使用已存在的pvc
	ExistingClaim: pvc-jenkins
	
	2. 屏蔽插件安装，如果能翻墙则不用
	InstallPlugins:
		#- kubernetes:1.1
		# - workflow-aggregator:2.5
		# - workflow-job:2.15
   		# - credentials-binding:1.13
   		# - git:3.6.4
	```
	

5. 修改模版文件

	```
	templates/jenkins-master-deployment.yaml 第一行改为：
	apiVersion: apps/v1
	
	```
6. 开始安装jenkins

	```
	helm install jenkins ./ -f values.yaml
	```
7. 添加路由ingressRoute(traefik）

	```
	kubectl create -f ingressRoute.yaml
	```
	
8. 查看jenkins密码

	```
	printf $(kubectl get secret --namespace default jenkins -o jsonpath="{.data.jenkins-admin-password}" | base64 --decode);echo
	```	