from django.urls import path
from autho import views

urlpatterns = [
    path('email_verification/<path:verification_code>/', views.email_verification, name='email_verification'),
    path('account_recovery/<path:verification_code>/', views.account_recovery, name='account_recovery'),
]
